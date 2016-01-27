<?php

/**************************************
** Created by Gravitate Cache Plugin **
**************************************/

/**
 * Extend the native wpdb class to add caching functionality
 */
class GRAV_CACHE_WRITE_WPDB extends GRAV_CACHE_WPDB
{
	private $write_dbh;


	final function query( $query )
	{
		$call_local = (defined('GRAV_CACHE_DB_WRITE_TO_LOCAL') && GRAV_CACHE_DB_WRITE_TO_LOCAL);
		$write_external = (defined('GRAV_CACHE_DB_WRITE_HOST') && GRAV_CACHE_DB_WRITE_HOST);

		// Check if Write DB is defined and is a Write Call
		if($write_external)
		{
			if(!$call_local)
			parent::check_select_query($query);

			if(preg_match( '/^\s*(create|alter|truncate|drop|insert|delete|update|replace)\s/i', $query))
			{
				if(empty($this->write_dbh))
				{
					$this->connect_write_db(); // Set $this->write_dbh;
				}

				if(!$this->write_dbh)
				{
					wp_die('Unable to connect to Write Database');
				}

				// Write to External Database
				$results = $this->write_query($query);

				if(!$call_local)
				parent::check_and_clear_write_query($query, $results);
			}
		}

		if($call_local || !$write_external)
		{
			// Call to Local Database
			$results = parent::query($query);
		}

		return $results;
	}

	final function connect_write_db()
	{
		if(empty($this->write_dbh) && defined('GRAV_CACHE_DB_WRITE_HOST') && defined('GRAV_CACHE_DB_WRITE_USER') && defined('GRAV_CACHE_DB_WRITE_NAME') && defined('GRAV_CACHE_DB_WRITE_PASSWORD'))
		{
			$new_link = defined( 'MYSQL_NEW_LINK' ) ? MYSQL_NEW_LINK : true;
			$client_flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;
			if($this->use_mysqli)
			{
				$this->write_dbh = mysqli_init();

				// mysqli_real_connect doesn't support the host param including a port or socket
                // like mysql_connect does. This duplicates how mysql_connect detects a port and/or socket file.
                $port = null;
                $socket = null;
                $host = GRAV_CACHE_DB_WRITE_HOST;
                $port_or_socket = strstr( $host, ':' );
                if ( ! empty( $port_or_socket ) )
                {
                    $host = substr( $host, 0, strpos( $host, ':' ) );
                    $port_or_socket = substr( $port_or_socket, 1 );
                    if ( 0 !== strpos( $port_or_socket, '/' ) )
                    {
                        $port = intval( $port_or_socket );
                        $maybe_socket = strstr( $port_or_socket, ':' );
                        if ( ! empty( $maybe_socket ) )
                        {
                                $socket = substr( $maybe_socket, 1 );
                        }
                    }
                    else
                    {
                            $socket = $port_or_socket;
                    }
                }

				if ( WP_DEBUG )
				{
                    mysqli_real_connect( $this->write_dbh, $host, GRAV_CACHE_DB_WRITE_USER, GRAV_CACHE_DB_WRITE_PASSWORD, null, $port, $socket, $client_flags );
                }
                else
                {
                    @mysqli_real_connect( $this->write_dbh, $host, GRAV_CACHE_DB_WRITE_USER, GRAV_CACHE_DB_WRITE_PASSWORD, null, $port, $socket, $client_flags );
                }

                if ( $this->write_dbh->connect_errno )
                {
	                $this->write_dbh = null;
	            }
			}
			else
			{
                if ( WP_DEBUG )
                {
                    $this->write_dbh = mysql_connect( GRAV_CACHE_DB_WRITE_HOST, GRAV_CACHE_DB_WRITE_USER, GRAV_CACHE_DB_WRITE_PASSWORD, $new_link, $client_flags );
                }
                else
                {
                    $this->write_dbh = @mysql_connect( GRAV_CACHE_DB_WRITE_HOST, GRAV_CACHE_DB_WRITE_USER, GRAV_CACHE_DB_WRITE_PASSWORD, $new_link, $client_flags );
                }
	        }

	        if($this->write_dbh)
	        {
	        	$this->set_charset( $this->write_dbh );
	        	$this->select( GRAV_CACHE_DB_WRITE_NAME, $this->write_dbh );
	        }
		}
	}

	public function write_query( $query )
	{
        if ( ! $this->ready ) {
                $this->check_current_query = true;
                return false;
        }

        /**
         * Filter the database query.
         *
         * Some queries are made before the plugins have been loaded,
         * and thus cannot be filtered with this method.
         *
         * @since 2.1.0
         *
         * @param string $query Database query.
         */
        $query = apply_filters( 'query', $query );

        $this->flush();

        // Log how the function was called
        $this->func_call = "\$db->query(\"$query\")";

        // If we're writing to the database, make sure the query will write safely.
        if ( $this->check_current_query && ! $this->check_ascii( $query ) ) {
                $stripped_query = $this->strip_invalid_text_from_query( $query );
                // strip_invalid_text_from_query() can perform queries, so we need
                // to flush again, just to make sure everything is clear.
                $this->flush();
                if ( $stripped_query !== $query ) {
                        $this->insert_id = 0;
                        return false;
                }
        }

        $this->check_current_query = true;

        // Keep track of the last query for debug..
        $this->last_query = $query;

        $this->_write_do_query( $query );

        // MySQL server has gone away, try to reconnect
        $mysql_errno = 0;
        if ( ! empty( $this->write_dbh ) ) {
                if ( $this->use_mysqli ) {
                        $mysql_errno = mysqli_errno( $this->write_dbh );
                } else {
                        $mysql_errno = mysql_errno( $this->write_dbh );
                }
        }

        if ( empty( $this->write_dbh ) || 2006 == $mysql_errno ) {
                if ( $this->check_connection() ) {
                        $this->_write_do_query( $query );
                } else {
                        $this->insert_id = 0;
                        return false;
                }
        }

        // If there is an error then take note of it..
        if ( $this->use_mysqli ) {
                $this->last_error = mysqli_error( $this->write_dbh );
        } else {
                $this->last_error = mysql_error( $this->write_dbh );
        }

        if ( $this->last_error ) {
                // Clear insert_id on a subsequent failed insert.
                if ( $this->insert_id && preg_match( '/^\s*(insert|replace)\s/i', $query ) )
                        $this->insert_id = 0;

                $this->print_error();
                return false;
        }

        if ( preg_match( '/^\s*(create|alter|truncate|drop)\s/i', $query ) ) {
                $return_val = $this->result;
        } elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $query ) ) {
                if ( $this->use_mysqli ) {
                        $this->rows_affected = mysqli_affected_rows( $this->write_dbh );
                } else {
                        $this->rows_affected = mysql_affected_rows( $this->write_dbh );
                }
                // Take note of the insert_id
                if ( preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
                        if ( $this->use_mysqli ) {
                                $this->insert_id = mysqli_insert_id( $this->write_dbh );
                        } else {
                                $this->insert_id = mysql_insert_id( $this->write_dbh );
                        }
                }
                // Return number of rows affected
                $return_val = $this->rows_affected;
        } else {
                $num_rows = 0;
                if ( $this->use_mysqli && $this->result instanceof mysqli_result ) {
                        while ( $row = @mysqli_fetch_object( $this->result ) ) {
                                $this->last_result[$num_rows] = $row;
                                $num_rows++;
                        }
                } elseif ( is_resource( $this->result ) ) {
                        while ( $row = @mysql_fetch_object( $this->result ) ) {
                                $this->last_result[$num_rows] = $row;
                                $num_rows++;
                        }
                }

                // Log number of rows the query returned
                // and return number of rows selected
                $this->num_rows = $num_rows;
                $return_val     = $num_rows;
        }

        return $return_val;
	}

	private function _write_do_query( $query )
	{
        if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
        {
                $this->timer_start();
        }

        if ( $this->use_mysqli ) {
                $this->result = @mysqli_query( $this->write_dbh, $query );
        } else {
                $this->result = @mysql_query( $query, $this->write_dbh );
        }
        $this->num_queries++;

        if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
                $this->queries[] = array( $query, $this->timer_stop(), $this->get_caller() );
        }
    }


    /**
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *     END of Custom Code
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 *
	 */



	public function update( $table, $data, $where, $format = null, $where_format = null )
	{
        if ( ! is_array( $data ) || ! is_array( $where ) ) {
                return false;
        }

        $data = $this->process_fields( $table, $data, $format );
        if ( false === $data ) {
                return false;
        }
        $where = $this->process_fields( $table, $where, $where_format );
        if ( false === $where ) {
                return false;
        }

        $fields = $conditions = $values = array();
        foreach ( $data as $field => $value ) {
                if ( is_null( $value['value'] ) ) {
                        $fields[] = "`$field` = NULL";
                        continue;
                }

                $fields[] = "`$field` = " . $value['format'];
                $values[] = $value['value'];
        }
        foreach ( $where as $field => $value ) {
                if ( is_null( $value['value'] ) ) {
                        $conditions[] = "`$field` IS NULL";
                        continue;
                }

                $conditions[] = "`$field` = " . $value['format'];
                $values[] = $value['value'];
        }

        $fields = implode( ', ', $fields );
        $conditions = implode( ' AND ', $conditions );

        $sql = "UPDATE `$table` SET $fields WHERE $conditions";

        $this->check_current_query = false;

        return $this->query( $this->prepare( $sql, $values ) );
    }

    public function delete( $table, $where, $where_format = null )
    {
        if ( ! is_array( $where ) ) {
                return false;
        }

        $where = $this->process_fields( $table, $where, $where_format );
        if ( false === $where ) {
                return false;
        }

        $conditions = $values = array();
        foreach ( $where as $field => $value ) {
                if ( is_null( $value['value'] ) ) {
                        $conditions[] = "`$field` IS NULL";
                        continue;
                }

                $conditions[] = "`$field` = " . $value['format'];
                $values[] = $value['value'];
        }

        $conditions = implode( ' AND ', $conditions );

        $sql = "DELETE FROM `$table` WHERE $conditions";

        $this->check_current_query = false;
        return $this->query( $this->prepare( $sql, $values ) );
    }

    public function insert( $table, $data, $format = null )
    {
        return $this->_insert_replace_helper( $table, $data, $format, 'INSERT' );
    }

    public function replace( $table, $data, $format = null )
    {
        return $this->_insert_replace_helper( $table, $data, $format, 'REPLACE' );
    }

    function _insert_replace_helper( $table, $data, $format = null, $type = 'INSERT' )
    {
        $this->insert_id = 0;

        if ( ! in_array( strtoupper( $type ), array( 'REPLACE', 'INSERT' ) ) ) {
                return false;
        }

        $data = $this->process_fields( $table, $data, $format );
        if ( false === $data ) {
                return false;
        }

        $formats = $values = array();
        foreach ( $data as $value ) {
                if ( is_null( $value['value'] ) ) {
                        $formats[] = 'NULL';
                        continue;
                }

                $formats[] = $value['format'];
                $values[]  = $value['value'];
        }

        $fields  = '`' . implode( '`, `', array_keys( $data ) ) . '`';
        $formats = implode( ', ', $formats );

        $sql = "$type INTO `$table` ($fields) VALUES ($formats)";

        $this->check_current_query = false;
        return $this->query( $this->prepare( $sql, $values ) );
    }
}

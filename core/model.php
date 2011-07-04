<?php
/* Model:
 * Derive all models from this class.
 * Provide the magic sauce for building parts of queries.
 *==============================================================================
 * -- Version alpha 0.1 --
 * This code is being released under an MIT style license:
 *
 * Copyright (c) 2010 Jillian Ada Burrows
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *------------------------------------------------------------------------------
 * Original Author: Jillian Ada Burrows
 * Email:           jill@adaburrows.com
 * Website:         <http://www.adaburrows.com>
 * Github:          <http://github.com/jburrows>
 * Facebook:        <http://www.facebook.com/jillian.burrows>
 * Twitter:         @jburrows
 *------------------------------------------------------------------------------
 * Use at your own peril! J/K
 * 
 */

class model extends db {
  protected $aspects = array();
  protected $aliases = array();
  protected $join_on = array();
  protected $default_fields = array();
  protected $insert_defaults = array();
  protected $update_defaults = array();
  protected $stat = array();
  protected $primary_aspect ='';
  protected $primary_key = '';

  /*
   * __consruct()
   * ------------
   * Sets up the scene for the magic that follows.\/\/007!
   */
  public function __construct() {
  global $aspects;
    $this->default_fields = array ( 'created', 'modified' );

    $this->insert_defaults = array (
      'created' => 'NOW()'
    );

    $this->update_defaults = array (
      'modified' => 'NOW()'
    );

    // Get the primary aspect -- first item in ordered hash
    $aspects_copy = $this->aspects;
    $this->primary_aspect = array_shift($aspects_copy);
    if($this->primary_aspect != null) {
        $aspect_fields = $aspects[$this->primary_aspect];
    } else {
        $this->primary_aspect = '';
        $aspect_fields = array();
    }
    //Get the primary field -- first item in ordered hash
    $this->primary_key = array_shift($aspect_fields);
    if($this->primary_key == null) {
        $this->primary_key = '';
    }
  }

  /*
   * get_all()
   * ---------
   * Select everything from the model
   */
  public function get_all() {
    $select = $this->build_select().';';
    return self::query_array($select);
  }

  /*
   * get_by_id()
   * -----------
   * Select a row with the given id
   */
  public function get_by_id($id) {
    $select = $this->build_select(NULL, NULL, array("{$this->primary_key}" => $id));
    return self::query_item($select);
  }

  /*
   * get_by_($field, $value)
   * -----------------------
   * Select a row with the given id
   */
  public function get_by_($data) {
    $select = $this->build_select(NULL, NULL, $data);
    return self::query_item($select);
  }

  /*
   * set()
   * ---------
   * Set the data for the model, creates it if it doesn't exist.
   * Updates using joins if provided data spans multiple aspects, $aspect is
   * ignored on update because all tables are computed from the data fields.
   * Inserts data into the default aspect if none is specified, otherwise,
   * inserts into specified aspect.
   */
  public function set($data, $aspect = NULL) {
    if (isset($data[$this->primary_key]) && $this->get_by_id($data[$this->primary_key])) {
      $query = $this->build_update($data);
    } else {
      if($aspect == NULL) {
          $aspect = $this->primary_aspect;
      }
      $query = $this->build_insert($data, $aspect);
    }
    $result = self::query_ins($query);
    return $result;
  }

  /*
   * delete()
   * --------
   * deletes data from the primary table in the model
   */
  public function delete($id, $aspects = NULL) {
      $query = $this->build_delete(array("{$this->primary_key}" => $id), $aspects);
      return self::query_ins($query);
  }

  /*
   * get_fields()
   * ------------
   * Takes an array possibly containing two keys: 'aspect' + 'fields'
   * Returns an array of strings in the form of: "`$table_name`.`$field_name`"
   *   to be used in a SQL query.
   */
  protected function get_fields($data = array()) {
  global $aspects;
      // Default: don't subset fields
      $subset_fields = false;
      // If we have a fields param and it's an array use it.
      if(isset($data['fields']) && is_array($data['fields'])) {
          $subset_fields = $data['fields'];
      }
      // Initialize variable to store resutant fields
      $total_fields = array();
      // If there is a specified aspect, lets use it.
      if(isset($data['aspect'])) {
          $current_aspects = array($data['aspect']);
          $primary_aspect = $data['aspect'];
      // Else, just use the models requested aspects and default primary aspect
      } else {
          $current_aspects = $this->aspects;
          $primary_aspect = $this->primary_aspect;
      }
      // Iterate over all current aspects
      foreach ($current_aspects as $aspect) {
          // Add default fields to primary aspect
          if ($aspect == $primary_aspect) {
              $iter_fields = array_merge($aspects[$aspect], $this->default_fields);
          // Don't merge in the default fields if not a primary aspect
          } else {
              $iter_fields = $aspects[$aspect];
          }
          // If we are subsetting the fields computer the intersection
          if($subset_fields) {
              $iter_fields = array_intersect($iter_fields, $subset_fields);
          }
          // Add each field into the total array, ready to be used in a query
          foreach ($iter_fields as $field) {
              $total_fields[$field] = "`{$aspect}`.`{$field}`";
          }
      }
      // Send it back, all shiny and new...
      return $total_fields;
  }

  /*
   * build_joins()
   * -------------
   * $from - primary table with which to join.
   * $tables - array of tables to join.
   * Also uses the $this->join_on array to creat the join conditions.
   * Returns a string containing a standard MySQL compatible join clause.
   */
  protected function build_joins($from, $tables) {
      $joins = array();
      // loop through tables to prepare join statements
      foreach($tables as $table) {
          // begin join statement
          $join = "JOIN `$table`";
          // if there are join on conditions, use them here.
          if (isset($this->join_on[$from])) {
              if (isset($this->join_on[$from][$table])) {
                  $join .= " ON ({$this->join_on[$from][$table]})";
              }
          }
          // Add join statement to the array of joins
          $joins[] = $join;
      }
      return $joins;
  }

  /*
   * build_where()
   * -------------
   * $data - contains fields and values to generate where statement.
   * Returns a string containing a standard SQL where clause.
   */
  protected function build_where($data) {
      $query_parts = array();
      $query_parts[] = 'WHERE';
      $where_parts = array();
      // We have joins for multiple tables (all aspects)
      $fields = $this->get_fields(array('fields' => $data));
      foreach($fields as $field => $full_field) {
          $where_parts[] = "{$full_field} = {$data[$field]}";
      }
      $query_parts[] = implode(' AND ', $where_parts);
      return implode(' ', $query_parts);
  }

  /*
   * build_select()
   * --------------
   * Builds a select statement with all joins if no aspect is given.
   */
  protected function build_select($aspect = NULL, $fields = NULL, $where = NULL) {
    $verb = 'SELECT';
    $fields = array();
    $table = '';
    $joins = array();

    // if we're selecting from only one aspect, there will be no joins
    if ($aspect != NULL) {
      $fields = $this->get_fields(array('aspect' => $aspect, 'fields' => $fields));
      // Set table to select from
      $table = "$aspect";
    } else {
      // We have joins for multiple tables (all aspects)
      $fields = array_values($this->get_fields(array('fields' => $fields)));
      // Store the tables we are using - mnemonic for ease of following the code
      $tables = $this->aspects;
      // Grab table to select from, and prevent it from being in the joins table
      $table = array_shift($tables);
      // Build joins
      $joins = $this->build_joins($table, $tables);
    }

    // finish building select statement
    $query_parts[] = $verb;
    $query_parts[] = implode(', ', $fields);
    $query_parts[] = "FROM `$table`";
    $query_parts[] = implode(' ', $joins);
    if(is_array($where)) {
        $query_parts[] = $this->build_where($where);
    }
    $query = implode(' ', $query_parts);
    return $query;
  }

  /*
   * build_insert()
   * --------------
   * Builds an insert statement for a specific aspect.
   */
  protected function build_insert($data, $aspect = NULL) {
    if($aspect == NULL) {
        $aspect = $this->primary_aspect;
    }
    $values = array();
    // Merge in the default values
    $data = array_merge($data, $this->insert_defaults);
    // Grab the fields for the given aspect
    $fields = $this->get_fields(array('aspect' => $aspect, 'fields' => array_keys($data)));
    // Build query
    $query  = "INSERT INTO `$aspect` (";
    $query .= implode(',', array_values($fields));
    $query .= ") VALUES (";
    // Iterate over each field and set the corresponding value
    foreach ($fields as $field_name => $field_query) {
      // Not currently using prepared statements, so clean it.
      $value = mysql_real_escape_string($data[$field_name]);
      // If it's numeric don't quote it
      if(is_numeric($value) || in_array($field_name, $this->default_fields)) {
        $values[] = $value;
      // If it's something else, quote it
      } else {
        $values[] = "'$value'";
      }
    }
    $query .= implode(',', $values);
    $query .= ");";
    return $query;
  }

  /*
   * build_update()
   * --------------
   * Builds an update statement with all joins if no aspect is given.
   */
  protected function build_update($data, $aspect = NULL) {
    $verb = 'UPDATE';
    $fields = array();
    $table = '';
    $joins = array();

    // Merge in the default values
    $data = array_merge($data, $this->update_defaults);
    // if we're selecting from only one aspect, there will be no joins
    if ($aspect != NULL) {
      $fields = $this->get_fields(array('aspect' => $aspect, 'fields' => array_keys($data)));
      // Set table to select from
      $table = "$aspect";
    } else {
      // We have joins for multiple tables (all aspects)
      $fields = array_values($this->get_fields(array('fields' => array_keys($data))));
      // Store the tables we are using - mnemonic for ease of following the code
      $tables = $this->aspects;
      // Grab table to select from, and prevent it from being in the joins table
      $table = array_shift($tables);
      // Build joins
      $joins = $this->build_joins($table, $tables);
    }

    // Build the set statements for the update query
    $statements = array();
    // Iterate over each field and set the corresponding value
    foreach ($fields as $field_name => $field_query) {
      // Not currently using prepared statements, so clean it.
      // TODO: Change this into a colon prefixed field_name for prepared statements
      $value = mysql_real_escape_string($data[$field_name]);
      // If it's numeric don't quote it
      if(is_numeric($value) || in_array($field_name, $this->default_fields)) {
        $statements[] = "$field_query=$value";
      // If it's something else, quote it
      } else {
        $statements[] = "$field_query='$value'";
      }
    }

    // finish building update statement
    $query_parts[] = $verb;
    $query_parts[] = implode(', ', $fields);
    $query_parts[] = "`$table`";
    $query_parts[] = implode(' ', $joins);
    $query_parts[] = 'SET';
    $query_parts[] = implode(', ', $statements);
    $query = implode(' ', $query_parts);

    // For update, we need a reference to the primary key
    $id = $data[$this->primary_key];
    $query .= " WHERE `{$this->primary_key}` = '{$id}';";
    return $query;
  }

  /*
   * build_delete()
   * --------------
   * Builds a delete statement with primary aspect if no aspect list is given.
   */
  protected function build_delete($data = NULL, $aspects = NULL) {
    $verb = 'DELETE';
    $fields = array();
    $table = '';
    $tables = array();
    $joins = array();

    // if we're selecting from only one aspect, there will be no joins
    if (is_string($aspects)) {
      $table = $aspects;
    } else if(is_array($aspects)) {
      // Store the tables we are using - mnemonic for ease of following the code
      $tables = $aspects;
      // Grab table to select from, and prevent it from being in the joins table
      $table = array_shift($tables);
      // Build joins
      $joins = $this->build_joins($table, $tables);
    } else {
        $table = $this->primary_aspect;
    }

    // finish building delete statement
    $query_parts[] = $verb;
    $query_parts[] = implode(', ', $tables);
    $query_parts[] = "FROM `$table`";
    $query_parts[] = implode(' ', $joins);
    // We have data to build a where clause
    if ($data != NULL && is_array($data)){
        $query_parts[] = $this->build_where($data);
    }
    $query = implode(" ", $query_parts);
    return $query;
  }

  /*
   * prepare_stat_subqueries()
   * -------------------------
   * Prepares individual statements
   * Expects classes that derive from this implement the comparison functions
   *   that return a SQL comparison "verb predicate" (eg. ' > NOW()').
   */
  protected function prepare_stat_subqueries($total_days) {
    $statements = array();
    foreach ($this->stat as $aspect => $params) {
      foreach ($params as $function => $comparison_key) {
        for ($i = 0; $i <= $total_days; $i++) {
          $statements[$aspect][] = "SELECT `{$this->primary_key}`, COUNT(`{$this->primary_key}`) AS `{$i}` FROM `{$aspect}` WHERE `$comparison_key`" . $this->$function($i)." GROUP BY `{$this->primary_key}`";
        }
      }
    }
    return $statements;
  }

  /*
   * build_stat_query()
   * ------------------
   * Builds entire stat query and returns it.
   */
  public function build_stat_query($total_days) {
    $verb             = 'SELECT';
    $stat_selections  = array("\n  `{$this->primary_aspect}`.`{$this->primary_key}` AS `{$this->primary_key}`");
    $stat_joins       = array("`{$this->primary_aspect}`");
    $query_parts      = array();
    $query            = '';

    $stat_statements  = $this->prepare_stat_subqueries($total_days);
    foreach ($stat_statements as $aspect => $stat_subqueries) {
      foreach ($stat_subqueries as $i => $stat_subquery) {
        $stat_selections[]  = "\n  COALESCE(`{$aspect}_table_{$i}`.`{$i}`, 0) AS `{$aspect}_{$i}`";
        $stat_join          = "\n  ( $stat_subquery ) AS `{$aspect}_table_{$i}`".
          "\n  ON `{$aspect}_table_{$i}`.`{$this->primary_key}` = `{$this->primary_aspect}`.`{$this->primary_key}`";
        $stat_joins[] = $stat_join;
      }
    }
    $query_parts[] = $verb;
    $query_parts[] = implode(', ', $stat_selections);
    $query_parts[] = "\nFROM";
    $query_parts[] = implode("\nLEFT OUTER JOIN", $stat_joins);
    $query_parts[] = "\nORDER BY `{$this->primary_aspect}`.`{$this->primary_key}`";
    $query = implode(' ', $query_parts);
    return $query;
  }

  /*
   * get_stats()
   * -----------
   * Builds + executes stat queries, parses results into the following format:
   *  $data[$aspect] = array($data_elements);
   */
  public function get_stats($total_days, $additional_clause = '') {
    if($additional_clause != '') {
      $additional_clause = ' '.$additional_clause;
    }
    $query = $this->build_stat_query($total_days).$additional_clause.';';
    $result = self::query_array($query);
    $data = array();
    foreach ($result as $i => $stats) {
      $temp = array($this->primary_key => $stats[$this->primary_key]);
      foreach ($this->stat as $aspect => $params) {
        for ($i = 0; $i <= $total_days; $i++) {
          $temp[$aspect][] = $stats["{$aspect}_{$i}"];
        }
      }
      $data[] = $temp;
    }
    return $data;
  }

}

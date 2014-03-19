<?php defined('SYSPATH') or die('No direct access allowed.');

/**
 * @package    Kodi/Datasource
 */

class DataSource_Hybrid_Agent {

	const COND_EQ = 0;
	const COND_BTW = 1;
	const COND_GT = 2;
	const COND_LT = 3;
	const COND_GTEQ = 4;
	const COND_LTEQ = 5;
	const COND_CONTAINS = 6;
	const COND_LIKE = 7;

	const VALUE_CTX = 10;
	const VALUE_PLAIN = 20;
	
	/**
	 *
	 * @var integer
	 */
	public $ds_id;
	
	/**
	 *
	 * @var string
	 */
	public $ds_name;
	
	/**
	 *
	 * @var array
	 */
	public $ds_fields = NULL;
	
	/**
	 *
	 * @var array
	 */
	public $ds_field_names = NULL;
	
	/**
	 *
	 * @var array
	 */
	public $sys_fields = NULL;
	
	public function __construct($ds_id, $ds_name) 
	{
		$this->ds_id = $ds_id;
		$this->ds_name = $ds_name;
	}

	/**
	 * 
	 * @return array
	 */
	public function get_fields()
	{
		if($this->ds_fields !== NULL)
		{
			return $this->ds_fields;
		}
		
		$this->ds_fields = $this->ds_field_names = array();
		
		$this->ds_fields = DataSource_Hybrid_Field_Factory::get_related_fields( $this->ds_id );
		
		foreach ($this->ds_fields as $id => $field)
		{
			$this->ds_field_names[$id] = $field->key;
		}
		
		return $this->ds_fields;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function get_system_fields()
	{
		if($this->sys_fields === NULL)
		{
			$this->sys_fields = array(
				'id' => DataSource_Hybrid_Field_Factory::get_field_from_array(array(
					'ds_id' => $this->ds_id, 
					'type' => 'primitive_integer', 
					'name' => 'ds.id', 
					'header' => 'ID',
					'system' => TRUE
				)),
				'header' => DataSource_Hybrid_Field_Factory::get_field_from_array(array(
					'ds_id' => $this->ds_id, 
					'type' => 'primitive_string', 
					'name' => 'd.header', 
					'header' => __('Header'),
					'system' => TRUE
				)),
				'created_on' => DataSource_Hybrid_Field_Factory::get_field_from_array(array(
					'ds_id' => $this->ds_id, 
					'type' => 'primitive_datetime', 
					'name' => 'd.created_on',
					'header' => __('Date created'),
					'system' => TRUE
				))
			);
		}

		return $this->sys_fields;
	}
	
	/**
	 * 
	 * @return array
	 */
	public function get_field_names() 
	{
		if($this->ds_fields === NULL)
		{
			$this->get_fields();
		}

		return $this->ds_field_names;
	}
	
	/**
	 * 
	 * @param array $fields
	 * @param array $order
	 * @param array $filter
	 * @return Database_Query_Builder_Select
	 */
	public function get_query_props(array $fields, array $fetched_objects = NULL, array $order = NULL, array $filter = NULL)
	{
		$result = DB::select('d.id', 'd.ds_id', 'd.header', 'd.published', 'd.created_on')
			->from(array('dshybrid_' . $this->ds_id,  'ds'))
			->join(array('dshybrid', 'd'))
				->on('d.id', '=', 'ds.id');
		
		$ds_fields = $this->get_fields();
		$sys_fields = $this->get_system_fields();
		
		$t = array($this->ds_id => TRUE);
		
		foreach ($fields as $i => $fid)
		{
			if(!isset($ds_fields[$fid])) continue;
			$field = $ds_fields[$fid];
			
			if( !($field instanceof DataSource_Hybrid_Field) ) continue;
			
			if(!isset($t[$field->ds_id])) 
			{
				$result->join(array('dshybrid_'.$field[$field->ds_id], 'd' . $i))
					->on('d' . $i, '=', ds.id);
	
				$t[$field[$field->ds_id]] = TRUE;
			}

			$result->select(array(DataSource_Hybrid_Field::PREFFIX . $field->key, $fid));
			
			$result = $field->get_query_props($result);

			unset($field);
		}
		
		if(!empty($order))
			$this->_fetch_orders($order, $t, $result);
		
		if(!empty($filter))
			$this->_fetch_filters($filter, $t, $result);

		return $result;
	}
	
	protected function _fetch_orders($orders, &$t, & $result)
	{
		$j = 0;
		$ds_fields = $this->get_fields();
		$sys_fields = $this->get_system_fields();
		
		foreach ($orders as $pos => $data)
		{
			$field = NULL;
			$fid = key($data);
			$dir = $data[key($data)];

			if(isset($ds_fields[$fid])) 
			{
				$field = $ds_fields[$fid];
			}
			else if(isset($sys_fields[$fid]))
			{
				$field = $sys_fields[$fid];
			}

			if( !($field instanceof DataSource_Hybrid_Field) ) continue;

			if(!isset($t[$field->ds_id])) 
			{
				$result->join(array('dshybrid_'. $field->ds_id, 'dorder' . $j))
					->on('dorder' . $j . '.id', '=', 'ds.id');

				$t[$field->ds_id] = TRUE;
			}

			$result = $field->sorting_condition($result, $dir);

			unset($field);

			$j++;
		}
	}
	
	protected function _fetch_filters($filters, & $t, & $result)
	{
		if(empty($filters)) return;

		$field_names = array_flip($this->get_field_names());
		$ds_fields = $this->get_fields();
		$sys_fields = $this->get_system_fields();

		foreach ($filters as $pos => $data)
		{
			$condition = $data['condition'];
			$type = $data['type'];
			$invert = !empty($data['invert']);
			$field = $data['field'];

			if($type == self::VALUE_PLAIN)
			{
				$value = $data['value'];
			}
			else
			{
				$value = Context::instance()->get($data['value']);
			}
			
			if(empty($value)) continue;

			$field_id = strpos($field, '$') == 1 
				? Context::instance()->get(substr($field, 1))
				: $field;

			if(isset($sys_fields[$field_id]))
			{
				$field = $sys_fields[$field_id];
			}
			else if(isset($ds_fields[$field_id]))
			{
				$field = $ds_fields[$field_id];
			}
			else if(isset($field_names[$field_id]))
			{
				$field = $ds_fields[$field_names[$field_id]];
			}
			else
				$field = NULL;

			if( !($field instanceof DataSource_Hybrid_Field) ) continue;
			
			if( !isset( $t[$field->ds_id] ) ) 
			{
				$result->join('dshybrid_' . $field->ds_id, 'dfilter' . $pos)
					->on('dfilter' . $pos . '.id', '=', 'ds.id');
				
				$t[$field->ds_id] = TRUE;
			}
	
			$in = FALSE;
			switch($condition) 
			{
				case self::COND_EQ:
					$value = explode(',', $value);
					
					if($value[0] == '*') 
						break;
					elseif( count( $value ) > 1)
						$in = TRUE;
					else
						$value = $value[0];
					break;
				case self::COND_CONTAINS:
					$value = explode(',', $value);
					$in = TRUE;
					break;
				case self::COND_BTW:
					$value = explode(',', $value, 2);
					if(count($value) != 2) break;
					break;
				default:
					$value = $value;
			}
			$in = $in === TRUE
				? 'IN' 
				: '=';
			
			if(is_array($value))
			{
				foreach($value as $i => $v)
				{
					if( preg_match('/now()|curdate()|curtime()|interval/i', $v ))
					{
						$value[$i] = DB::expr($v);
					}
				}
			}
			else
				if( preg_match('/now()|curdate()|curtime()|interval/i', $value ))
				{
					$value = DB::expr($value);
				}
	
			$conditions = array($in, 'BETWEEN', '>', '<', '>=', '<=', 'IN', 'LIKE');
			$condition = Arr::get($conditions, $condition, '=');
			
			if($invert === TRUE)
			{
				switch ($condition)
				{
					case '>':
						$condition = '<=';
						break;
					case '<':
						$condition = '>=';
						break;
					case '=':
						$condition = '!=';
						break;
					case 'IN':
					case 'LIKE':
					case 'BETWEEN':
						$condition = 'NOT ' . $condition;
						break;
					case '>=':
						$condition = '<';
						break;
					case '<=':
						$condition = '>';
						break;
				}
			}

			$type = NULL;
			$fid = NULL;
			foreach($ds_fields as $id => $f) 
			{
				if($f->key == $field->key) 
				{
					$type = $f->type;
					$fid = $id;
				}
			}
			
			$result = $field->filter_condition($result, $condition, $value);
			
		}
	}

	/**
	 *
	 * @var array 
	 */
	protected static $_instance = array();

	/**
	 * 
	 * @param string|integer $ds_id
	 * @param boolean $only_sub
	 * @return DataSource_Hybrid_Agent
	 */
	public static function instance($ds_id)
	{
		if(isset(self::$_instance[$ds_id]))
		{
			return self::$_instance[$ds_id];
		}
		
		$result = DB::select('id', 'name')
			->from('datasources')
			->where('type', '=', 'hybrid')
			->where('id', '=', (int) $ds_id)
			->execute();
		
		if($result->count() > 0)
		{
			$current = $result->current();
			$ds_id = $current['id'];
			$ds_name = $current['name'];
			
			self::$_instance[$ds_id] = new DataSource_Hybrid_Agent($ds_id, $ds_name);
		}
		else
		{
			self::$_instance[$ds_id] = NULL;
		}
		
		return self::$_instance[$ds_id];
	}
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DeviceData;
use App\AlarmType;
use App\Alarm;
use App\Device;
use App\Machine;
use DB;

class AlarmController extends Controller
{
    public function getProductAlarms(Request $request) {
		$alarms = DeviceData::where('machine_id', 1)
						->where('tag_id', 27)
						->orderBy('timestamp')
						->where('timestamp', '>', strtotime($request->from))
						->where('timestamp', '<', strtotime($request->to))
						->get();

		return response()->json(compact('alarms'));
	}

	public function getAlarmTypesByMachineId($id) {
		$alarm_types = AlarmType::select('name')
							->where('machine_id', $id)
							->get();

		return $alarm_types;
	}
	
	public function getMachineIdByMachineName($machine_name) {		
		$machine_id = Machine::select('id')->where('name', $machine_name)->get()->first()->id;

		return $machine_id;
	}

	public function getAssignedMachinesByCompanyId($company_id) {
		$query = 'SELECT machines.id, machines.name FROM devices INNER JOIN machines ON devices.machine_id = machines.id WHERE devices.company_id = ' . $company_id;
		$assigned_machines = DB::select(DB::raw($query));

		return $assigned_machines;
	}

	public function getBasicDeviceData($company_id, $machine_id = 0, $dates = NULL) {
		if ($machine_id) {
			$query = 'SELECT alarm_types.name, alarm_types.machine_id, alarm_types.tag_id, alarm_types.machine_id FROM alarm_types WHERE alarm_types.machine_id = '. $machine_id;
		} else {
			$assigned_devices = Device::select('machine_id')->where('company_id', $company_id)->get();
			$machine_ids = [];
			foreach($assigned_devices as $assigned_device) {
				$machine_ids[] = $assigned_device['machine_id'];
			}
	
			$query = 'SELECT alarm_types.name, alarm_types.machine_id, alarm_types.tag_id, alarm_types.machine_id FROM alarm_types WHERE alarm_types.machine_id IN (' 
				. implode("," , $machine_ids) . 
				')';
		}
		$alarm_types = DB::select(DB::raw($query));

		$tag_ids = [];
		foreach($alarm_types as $alarm_type) {
			$tag_ids = array_merge($tag_ids, json_decode($alarm_type->tag_id));	
		}

		$query = 'SELECT device_data.tag_id, device_data.timestamp, device_data.values, device_data.machine_id from device_data WHERE device_data.tag_id IN (' 
					. implode("," , $tag_ids) . ') AND device_data.customer_id = ' . $company_id;

		if ($machine_id) {
			$query .= ' AND device_data.machine_id = ' . $machine_id;
		}

		if ($dates) {
			$query .= ' AND device_data.timestamp BETWEEN ' . strtotime($dates[0]) . ' AND ' . strtotime($dates[1]);
		}

		$device_data = DB::select(DB::raw($query));

		foreach($device_data as $item) {
			$sum = 0;
			$value = json_decode($item->values);
			foreach($value as $num) {
				$sum += $num;
			}
			$item->values = $sum;
			$item->times = 1;
			$alarm_type_name = AlarmType::select('name')->where('machine_id', $item->machine_id)->where('tag_id', 'LIKE', '%' . $item->tag_id . '%')->get();
			$item->alarm_type_name = $alarm_type_name->first()->name;
		}

		return $device_data;
	}

	public function gatherAlarmsByType($company_id, $machine_id = 0, $dates = NULL) {
		$device_data = $this->getBasicDeviceData($company_id, $machine_id, $dates);

		foreach($device_data as $key => $item) {
			for($i = $key + 1; $i < count($device_data); $i ++) {
				if ($item->machine_id == $device_data[$i]->machine_id && $item->tag_id == $device_data[$i]->tag_id) {
					$item->times ++;
					$item->values += $device_data[$i]->values;
				}
			}
		}

		usort($device_data, function ($a, $b) {
			return $b->values - $a->values;
		});	

		return $device_data;
	}

	/// --- Main Functions ---- ///
	public function getSeverityByCompanyId(Request $request) {

		$device_data = $this->gatherAlarmsByType($request->company_id, 0, $request->dates);
		$severity = [];
		if ($device_data) {
			$severity[] = $device_data[0];
			$i = 1;
			foreach($device_data as $item) {
				if ($severity[$i - 1]->tag_id != $item->tag_id || $severity[$i - 1]->machine_id != $item->machine_id ) {
					$severity[] = $item;
					$i ++;
				}
				if ($i == 3)
					break;
			}
		}		
		return response()->json(compact('severity'));
	}

	public function getAlarmsPerTypeByMachine(Request $request) {

		$machine_id = $this->getMachineIdByMachineName($request->machine_name);
		$device_data = $this->gatherAlarmsByType($request->company_id, $machine_id, $request->dates);
		$alarm_type_names = $this->getAlarmTypesByMachineId($machine_id);

		$alarms = [];
		foreach($alarm_type_names as $alarm_type) {
			$flag = 0;
			foreach($device_data as $item) {
				if ($alarm_type->name == $item->alarm_type_name) {
					$alarms[] = [
						'name'		=>	$item->alarm_type_name,
						'values'	=> 	$item->values
					];
					break;
				}
			}
		}
		return response()->json(compact('alarms'));
	}

	public function getAlarmsDistributionByMachine(Request $request) {

		$machine_id = $this->getMachineIdByMachineName($request->machine_name);
		$device_data = $this->getBasicDeviceData($request->company_id, $machine_id, $request->dates);
		$alarm_type_names = $this->getAlarmTypesByMachineId($machine_id);

		$data = [0];
		if (!count($alarm_type_names))
			$data = [0, 0];
		foreach($alarm_type_names as $item) {
			$results[] = (object) array(
				'name' => $item->name,
				'data' => $data
			);
		}
		foreach($device_data as $row) {
			foreach($results as $item) {
				$val = 0;
				if ($item->name == $row->alarm_type_name) {
					$val = $row->values;
				}
				$item->data[] = $val;
			}
		}
		return response()->json(compact('results'));
	}

	public function getAlarmsAmountPerMachineByCompanyId(Request $request) {

		$device_data = $this->gatherAlarmsByType($request->company_id, 0, $request->dates);
		$results = $this->getAssignedMachinesByCompanyId($request->company_id);

		foreach($results as $machine) {
			$value = 0;
			$tag_ids = [];
			foreach($device_data as $item) {
				if (!in_array($item->tag_id, $tag_ids)) {
					if ($item->machine_id == $machine->id) {
						$value += $item->values;
						$tag_ids[] = $item->tag_id;
					}
				}				
			}
			$machine->amount = $value;
		}
		return response()->json(compact('results'));
	}
}

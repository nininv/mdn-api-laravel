<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DeviceData;
use App\AlarmType;
use App\Alarm;
use App\Device;
use App\Machine;
use App\Tag;
use App\Company;
use App\TeltonikaConfiguration;
use App\AlarmStatus;
use App\Role;
use App\ActiveAlarms;
use DB;
use \stdClass;

class AlarmController extends Controller
{
	public function getAlarmsForCustomerDevices(Request $request) {
		$user = $request->user('api');

		if(!$user) {
			return response()->json('Unauthorized', 401);
		}

		$devices = $user->company->devices;

		foreach ($devices as $key => $device) {
			$alarm_types = AlarmType::where('machine_id', $device->machine_id)->get();
			$alarm_tag_ids = [];

			foreach ($alarm_types as $key => $alarm_type) {
				$alarm_tag_ids[] = $alarm_type->tag_id;
			}

			$device->alarms_count = DeviceData::where('device_id', $device->serial_number)->whereIn('tag_id', $alarm_tag_ids)->count();
		}

		return response()->json(compact('devices'));
	}

    public function getProductAlarms(Request $request) {
		$alarm_types = AlarmType::where('machine_id', $request->machineId)->orderBy('id')->get();
		$tag_ids = $alarm_types->unique('tag_id')->pluck('tag_id');

		$alarms_object = Alarm::where('serial_number', $request->serialNumber)
								->whereIn('tag_id', $tag_ids)
								->orderBy('timestamp', 'DESC')
								->get()
								->unique('tag_id');

		$alarms = [];

		foreach ($alarms_object as $alarm_object) {
			$value32 = json_decode($alarm_object->values);

			$alarm_types_for_tag = $alarm_types->filter(function ($alarm_type, $key) use ($alarm_object) {
			    return $alarm_type->tag_id == $alarm_object->tag_id;
			});

			foreach ($alarm_types_for_tag as $alarm_type) {

				$alarm = new stdClass();

				$alarm->id = $alarm_object->id;
				$alarm->tag_id = $alarm_object->tag_id;
				$alarm->timestamp = $alarm_object->timestamp * 1000;
				if($alarm_type->bytes == 0 && $alarm_type->offset == 0)
					$alarm->active = $value32[0];
				else if($alarm_type->bytes == 0 && $alarm_type->offset != 0 && isset($value32[$alarm_type->offset])) {
					$alarm->active = !!$value32[$alarm_type->offset] == true;
				} else if($alarm_type->bytes != 0) {
					$alarm->active = ($value32[0] >> $alarm_type->offset) & $alarm_type->bytes;
				}

				$alarm->type_id = $alarm_type->id;

				array_push($alarms, $alarm);
			}
		}

		return response()->json(compact('alarms', 'alarm_types'));
	}

	public function getProductAlarmHistory(Request $request) {
		$device_id = TeltonikaConfiguration::where('plc_serial_number', $request->serialNumber)->first()->teltonika_id;
		$alarms_object = AlarmStatus::where('machine_id', $request->machineId)
									->where('device_id', $device_id)
									->where('timestamp', '>', $request->from)
									->where('timestamp', '<', $request->to)
									->orderBy('tag_id')
									->orderBy('offset')
									->orderBy('timestamp')
									->get();

		$alarm_types = AlarmType::where('machine_id', $request->machineId)->orderBy('id')->get();
		$alarms = [];

		foreach ($alarm_types as $alarm_type) {
			$alarms_for_tag = $alarms_object->filter(function ($alarm_object, $key) use ($alarm_type) {
				return $alarm_object->tag_id == $alarm_type->tag_id && $alarm_object->offset == $alarm_type->offset;
			});

			if (count($alarms_for_tag) > 0) {
				$alarm_info = new stdClass();
				$temp = 0;
				$completed_array = false;
				$i = 0;

				foreach ($alarms_for_tag as $key => $alarm_for_tag) {
					$alarm_info->name = $alarm_type->name;
					if ($alarm_for_tag->is_activate && $temp == 0) {
						$temp = 1;
						$alarm_info->activate = $alarm_for_tag->timestamp;
					} else if (!$alarm_for_tag->is_activate && $temp == 1) {
						$temp = 0;
						$alarm_info->resolve = $alarm_for_tag->timestamp;
						$completed_array = true;
					}

					if ($completed_array) {
						array_push($alarms, $alarm_info);
						$completed_array = false;
						$alarm_info = new stdClass();
					}

					if ($i == count($alarms_for_tag) - 1 && $temp == 1) {
						$alarm_info->resolve = -1;
						array_push($alarms, $alarm_info);
						$alarm_info = new stdClass();
					}

					$i++;
				}
			}
		}

		return response()->json(compact('alarms'));
	}

	public function getAlarmsReports(Request $request) {
		$user = $request->user('api');
		$company_id = $request->companyId == 0 ? $user->company->id : $request->companyId;
		$device_ids = Device::where('company_id', $company_id)->pluck('serial_number');

		$active_alarms = ActiveAlarms::whereIn('device_id', $device_ids)->get();

		foreach ($active_alarms as $active_alarm) {
			$active_alarm['machineName'] = Machine::where('id', $active_alarm->machine_id)->first()->name;
			$active_alarm['alarmName'] = AlarmType::where('tag_id', $active_alarm->tag_id)->where('offset', $active_alarm->offset)->first()->name;
			$active_alarm['deviceData'] = Device::where('serial_number', $active_alarm->device_id)->first();
		}

		$alarmsCount = count($active_alarms);

		return response()->json(compact('active_alarms', 'alarmsCount'));
	}

	public function getAlarmTypesByMachineId($id) {
		$alarm_types = AlarmType::select('name')
							->where('machine_id', $id)
							->get();

		return $alarm_types;
	}

	public function getMachineIdByMachineName($machine_name)
    {
		return Device::select('machine_id', 'serial_number')->where('customer_assigned_name', $machine_name)->first();
	}

	public function getAssignedMachinesByCompanyId($company_id) {
		// $query = 'SELECT machines.id, machines.name FROM devices INNER JOIN machines ON devices.machine_id = machines.id WHERE devices.company_id = ' . $company_id;
		// $assigned_machines = DB::select(DB::raw($query));

		$assigned_machines = Device::select('machines.id', 'machines.name')
									->join('machines', 'devices.machine_id', '=', 'machines.id')
									->where('devices.company_id', $company_id)
									->get();
		return $assigned_machines;
	}
}

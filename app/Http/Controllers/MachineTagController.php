<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\MachineTag;
use App\AlarmType;
use App\Device;
use App\UserCustomizations;
use App\DefaultCustomization;

use \stdClass;

class MachineTagController extends Controller
{
    public function getMachineTags(Request $request) {
		$user = $request->user('api');
		$serialNumber = $request->serialNumber;
		$user_customization = UserCustomizations::where('user_id', $user->id)->first();
    	$tags = MachineTag::where('configuration_id', $request->machineId)->orderBy('name')->get();
    	$alarm_tags = AlarmType::where('machine_id', $request->machineId)->orderBy('name')->get();
		$default_setting = DefaultCustomization::where('machine_id', $request->machineId)->first();

    	$tags = $tags->merge($alarm_tags);

		if ($user_customization) {
			$option = json_decode($user_customization->customization);
			if (isset($option->$serialNumber)) {
				$customization = $option->$serialNumber->selectedTags;
			} else if ($default_setting) {
				$customization = json_decode($default_setting->customization);
			} else {
				$customization = [];
			}
		} else if ($default_setting) {
			$customization = json_decode($default_setting->customization);
		} else {
			$customization = [];
		}

    	return response()->json(compact('tags', 'customization'));
    }

	public function getMachinesTags(Request $request) {
		$device_ids = $request->deviceIds;
		$tags = [];
		foreach ($device_ids as $key => $device_id) {
			$device = Device::where('device_id', $device_id)->first();
			$machine_data = new stdClass();
			$machine_data->device_id = $device_id;

			$machine_tags = MachineTag::where('configuration_id', $device->machine_id)->orderBy('name')->get();
    		$alarm_tags = AlarmType::where('machine_id', $device->machine_id)->orderBy('name')->get();

    		$machine_tags = $machine_tags->merge($alarm_tags);
			$machine_data->tags = $machine_tags;

			array_push($tags, $machine_data);
		}
		return response()->json(compact('tags'));
	}
}

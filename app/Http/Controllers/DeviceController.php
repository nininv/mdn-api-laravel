<?php

namespace App\Http\Controllers;

use App\Http\Resources\DownTimeTableDataResource;
use App\QueryFilters\DowntimeFilter;
use App\QueryFilters\MachineFilter;
use App\QueryFilters\Sort;
use App\Running;
use App\Setting;
use Illuminate\Http\Request;
use App\Device;
use App\Company;
use App\Machine;
use App\SavedMachine;
use App\Zone;
use App\Location;
use App\DeviceData;
use App\EnergyConsumption;
use App\Utilization;
use App\TeltonikaConfiguration;
use App\Tag;
use App\EnabledProperty;
use App\Downtimes;
use App\DowntimeType;
use App\DowntimeReason;
use App\Role;
use App\AvailabilityPlanTime;
use App\Timezone;
use App\Idle;
use App\ActiveAlarms;
use App\Threshold;
use App\UserCustomizations;
use App\Imports\DevicesImport;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use GuzzleHttp\Client;
use Validator;
use \stdClass;

class DeviceController extends Controller
{
    /*
    SIM status:
        1: Not initialized
        2: Active
        1: Suspended
        4: Scrapped
    */
    private $suspendURL = "https://prismproapi.sandbox.koretelematics.com/4/TransactionalAPI.svc/json/suspendDevice";
    private $activateURL = "https://prismproapi.sandbox.koretelematics.com/4/TransactionalAPI.svc/json/activateDevice";
    private $queryURL = "https://prismproapi.sandbox.koretelematics.com/4/TransactionalAPI.svc/json/queryDevice";

    private $teltonika_import_url = "https://rms.teltonika-networks.com/api/devices?limit=100";
    private $bearer_token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJqdGkiOiI2MjQxODgwMjFiMWIwY2UwNTA5ZDE3OWUzY2IxMDgxOGM2YmUzMjlhNjY3NTMwOGU0ZGI4NTEwODU4OThlZGUzNjY0NDQwODA1MDkwZWJjNSIsImlzcyI6Imh0dHBzOlwvXC9ybXMudGVsdG9uaWthLW5ldHdvcmtzLmNvbVwvYWNjb3VudCIsImlhdCI6MTYwNTY2NzMyNywibmJmIjoxNjA1NjY3MzI3LCJleHAiOjE2MzcyMDMzMjcsInN1YiI6IjI3OTcwIiwiY2xpZW50X2lkIjoiOTEyM2VhNjYtMmYxZC00MzljLWIxYzItMzExYWMwMTBhYWFkIiwiZmlyc3RfcGFydHkiOmZhbHNlfQ.I0kEBbsYDzIsBr3KFY9utxhSuKLM0zRgrPUBcUUNrIU3V58tce3LUgfV6r8yip5_pOe3ybVQdEoyIXNuehPUDIa8ZxJYadGw15cs9PLDyvM00ipAggnCgi0QinxUcb_5QjaMqfemhTlil9Zquly-P9tGy8GuT-QKAxMMCwGgou_LA3JH-5c7hoImbINMMyWQaHIrK3IiSVXyb0k_tP2tczy7TIjM5NFdzTMZXlVYEwTRZJ7U-_Vyb0ZnyyTJ_Y6_6CNp79vtQ8kVD_Xs_MVCQ0vQbO9qPRAxNu8noq7ZVo1eRdc1Q411puyzm3MeVSg1bWqqG4QboGiMYTyYclwhqA";
    private $timeshift = 0;

    public function __construct()
    {
        $user = auth('api')->user();
        $timezone = Timezone::where('id', $user->profile->timezone)->first();
        if ($timezone) {
            date_default_timezone_set($timezone->name);

            $this->timeshift = date('Z');
        }
    }

    public function getPlcStatus($deviceId)
    {
        $getLink = 'https://rms.teltonika-networks.com/api/devices/' . $deviceId;

        $client = new Client();

        try {
            $response = $client->get(
                $getLink,
                [
                    'headers' => [
                        'Authorization' => "Bearer " . $this->bearer_token,
                        'Accept' => "application/json"
                    ]
                ]
            );

            return json_decode($response->getBody())->data;
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }

    public function isPlcRunning($machineId, $serialNumber)
    {
        $tag = Tag::where('configuration_id', $machineId)
            ->where('tag_name', Tag::NAMES['RUNNING'])
            ->first();

        if ($tag) {
            $running = Running::where('serial_number', $serialNumber)
                ->where('tag_id', $tag->tag_id)
                ->latest('timestamp')
                ->first();

            if ($running) {
                return json_decode($running->values)[0];
            }
        }

        return false;
    }

    public function isMachineRunning($deviceId, $machineId)
    {
        $tag = Tag::where('configuration_id', $machineId)
            ->where('tag_name', Tag::NAMES['RUNNING'])
            ->first();

        if ($tag) {
            $running = Running::where('device_id', $deviceId)
                ->where('tag_id', $tag->tag_id)
                ->latest('timestamp')
                ->first();

            if ($running) {
                return json_decode($running->values)[0];
            }
        }

        return false;
    }

    public function isMachineIdle($deviceId, $machineId)
    {
        $tag = Tag::where('configuration_id', $machineId)
            ->where('tag_name', Tag::NAMES['IDLE'])
            ->first();

        if ($tag) {
            $idle = Idle::where('device_id', $deviceId)
                ->where('tag_id', $tag->tag_id)
                ->latest('timestamp')
                ->first();

            if ($idle) {
                return json_decode($idle->values)[0];
            }

            return false;
        }
    }

    public function isPlcAlarmActivated($deviceId, $machineId)
    {
        $activeAlarm = ActiveAlarms::where('device_id', $deviceId)
            ->where('machine_id', $machineId)
            ->first();

        if ($activeAlarm) return true;

        return false;
    }

    public function isThresholdsActivated($teltonikaId, $machineId, $userId)
    {
        $threshold = Threshold::where('device_id', $teltonikaId)
            ->where('user_id', $userId)
            ->where('threshold_activated', true)
            ->first();

        if ($threshold) return true;

        return false;
    }

    public function isApproachingActivated($teltonikaId, $machineId, $userId)
    {
        $approaching = Threshold::where('device_id', $teltonikaId)
            ->where('user_id', $userId)
            ->where('approaching_activated', true)
            ->first();

        if ($approaching) return true;

        return false;
    }

    public function getACSDevices(Request $request)
    {
        $devices_paginated = Device::orderBy('sim_status')
            ->orderBy('id')
            ->whereVisibleOnly()
            ->whereSimActive($request->filterForm['filters'] ?? [])
            ->wherePlcLink($request->filterForm['filters'] ?? [])
            ->whereRegistered($request->filterForm['filters'] ?? [])
            ->whereSearchQuery($request->filterForm['searchQuery'] ?? '')
            ->with('checkin')
            ->paginate(config('settings.num_per_page'));


        $devices_paginated_array = $devices_paginated->toArray();

        $devices = collect($devices_paginated_array['data'])
            ->each(function ($device, $key) {
                try {

                    if (!isset($device['public_ip_sim']) || !$device['public_ip_sim']) {
                        $device['public_ip_sim'] = $this->publicIP($device['iccid'])->public_ip_sim ?? null;
                    }

                    if (!isset($device['sim_status']) || !$device['sim_status']) {
                        $device['sim_status'] = $this->querySIM($device['iccid'])->sim_status ?? null;
                    }

                    if (!isset($device['carrier']) || !$device['carrier']) {
                        $device->carrier = $this->carrierFromKoreAPI($device['iccid'])->carrier ?? null;
                    }

                } catch (\Exception $e) {

                }
            });

        return response()->json([
            'is_visible_only' => (new Setting())->getTypeVisibleValue() == 'configured',
            'hidden_devices' => Device::count() - $devices->count(),
            'devices' => $devices,
            'companies' => Company::select('id', 'name')->get(),
            'last_page' => $devices_paginated_array['last_page'],
            'first_page_url' => $devices_paginated_array['first_page_url'],
            'from' => $devices_paginated_array['from'],
            'last_page_url' => $devices_paginated_array['last_page_url'],
            'next_page_url' => $devices_paginated_array['next_page_url'],
            'path' => $devices_paginated_array['path'],
            'per_page' => $devices_paginated_array['per_page'],
            'prev_page_url' => $devices_paginated_array['prev_page_url'],
            'to' => $devices_paginated_array['to'],
            'total' => $devices_paginated_array['total'],
        ]);
    }

    public function getDeviceConfiguration(Request $request, $id)
    {
        $user = $request->user('api');

        $teltonika_configuration = TeltonikaConfiguration::where('teltonika_id', $id)->first();

        if (!$teltonika_configuration) {
            return response()->json([
                'status' => 'device_not_connected',
                'message' => 'Device not connected yet'], 404);
        }

        $configuration = new stdClass();

        $configuration->isTcuConnected = $teltonika_configuration->isTcuConnected();
        $configuration->plcMachineId = $teltonika_configuration->plcMachine()->id;
        $configuration->plcMachineName = $teltonika_configuration->plcMachine()->name;
        $configuration->plcSerialNumber = $teltonika_configuration->plcSerialNumber();
        $configuration->tcuMachineName = 'TrueTemp TCU';
        $configuration->isTcuConnected = $teltonika_configuration->isTcuConnected();
        $configuration->tcuSerialNumber = $teltonika_configuration->tcuSerialNumber();
        $configuration->plcAnalyticsGraphs = $teltonika_configuration->plcAnalyticsGraphs();
        $configuration->plcPropertiesGraphs = $teltonika_configuration->plcPropertiesGraphs();
        $configuration->plcEnabledAnalyticsGraphs = $teltonika_configuration->plcEnabledAnalyticsGraphs($user->id, $teltonika_configuration->plc_serial_number);
        $configuration->plcEnabledPropertiesGraphs = $teltonika_configuration->plcEnabledPropertiesGraphs($user->id, $teltonika_configuration->plc_serial_number);
        $configuration->tcuAnalyticsGraphs = $teltonika_configuration->tcuAnalyticsGraphs();
        $configuration->tcuPropertiesGraphs = $teltonika_configuration->tcuPropertiesGraphs();
        $configuration->tcuEnabledAnalyticsGraphs = $teltonika_configuration->tcuEnabledAnalyticsGraphs($user->id, $teltonika_configuration->tcu_serial_number);
        $configuration->tcuEnabledPropertiesGraphs = $teltonika_configuration->tcuEnabledPropertiesGraphs($user->id, $teltonika_configuration->tcu_serial_number);

        return response()->json(compact('configuration'));
    }

    public function updateEnabledProperties(Request $request)
    {
        $user = $request->user('api');

        $obj = EnabledProperty::firstOrCreate([
            'serial_number' => $request->serial_number,
            'user_id' => $user->id,
        ], [
            'property_ids' => json_encode($request->enabled_properties)
        ]);

        $ids = [];
        $existing_ids = json_decode($obj->property_ids);

        if ($request->isImportant) {
            foreach ($existing_ids as $value) {
                if ($value > 100) array_push($ids, $value);
            }

        } else {
            foreach ($existing_ids as $value) {
                if ($value < 100) array_push($ids, $value);
            }
        }

        EnabledProperty::whereId($obj->id)->update([
            'property_ids' => json_encode(array_merge($ids, $request->enabled_properties))
        ]);

        return response()->json('Updated successfully');
    }

    public function toggleActiveDevices()
    {
        $is_all_devices_visible_object = DB::table('settings')->where('type', 'is_all_devices_visible');

        if ($is_all_devices_visible_object->first()->value == 'configured')
            $is_all_devices_visible_object->update([
                'value' => 'all'
            ]);
        else {
            $is_all_devices_visible_object->update([
                'value' => 'configured'
            ]);
        }

        return response()->json([
            'message' => 'Successfully updated'
        ]);
    }

    public function getAllDevices()
    {
        $devices = Device::orderBy('sim_status', 'ASC')
            ->where('iccid', '<>', 0)
            ->whereNotNull('iccid')
            ->select('name', 'id', 'customer_assigned_name', 'tcu_added')
            ->get();

        return response()->json(compact('devices'));
    }

    public function importDevices(Request $request, Client $client)
    {
        $existing_devices = Device::all();
        $numAdded = 0;
        $numDuplicates = 0;

//        $client = new Client();
        try {
            $response = $client->get(
                $this->teltonika_import_url,
                [
                    'headers' => [
                        'Authorization' => "Bearer " . $this->bearer_token
                    ]
                ]
            );

            $devices = json_decode($response->getBody())->data;
            foreach ($devices as $key => $device) {
                $exisitng_device = $existing_devices->where('serial_number', $device->serial)->first();
                if ($exisitng_device) {
                    $exisitng_device->update([
                        'name' => $device->name,
                        'lan_mac_address' => $device->mac,
                    ]);
                    $numDuplicates++;
                    continue;
                } else {
                    Device::create([
                        'device_id' => $device->id,
                        'name' => $device->name,
                        'customer_assigned_name' => $device->name,
                        'serial_number' => $device->serial,
                        'imei' => $device->imei,
                        'lan_mac_address' => $device->mac,
                        'iccid' => substr($device->iccid, 0, -1),
                        'public_ip_sim' => null,
                        'machine_id' => null,
                        'company_id' => null,
                        'registered' => false
                    ]);
                    $numAdded++;
                }
            }
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }


        return response()->json([
            'numAdded' => $numAdded,
            'numDuplicates' => $numDuplicates
        ]);
    }

    public function deviceAssigned(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plc_ip' => 'required',
            'device_id' => 'required',
            'company_id' => 'required',
            'machine_id' => 'required',
            'device_name' => 'required|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $device = Device::findOrFail($request->device_id);

        $device->company_id = $request->company_id;
        $device->machine_id = $request->machine_id;
        $device->tcu_added = $request->tcu_added;
        $device->plc_ip = $request->plc_ip;
        $device->customer_assigned_name = $request->device_name;

        $device->save();

        return response()->json('Successfully assigned.');
    }

    /*
        Assign zone to a device and update machine name in machine mapping page
    */
    public function updateCustomerDevice(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'customer_assigned_name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $device = Device::findOrFail($request->id);

        if ($request->zone_id) {
            $location = Zone::findOrFail($request->zone_id)->location;
            $device->location_id = $location->id;
        } else {
            $device->location_id = 0;
        }

        $device->zone_id = $request->zone_id;

        $device->customer_assigned_name = $request->customer_assigned_name;

        $device->save();

        return response()->json('Successfully assigned.');
    }

    public function sendDeviceConfiguration(Request $request)
    {
        $device = Device::findOrFail($request->device_id);

        $device_configuration = new stdClass();
        $device_configuration_plc = new stdClass();
        $device_configuration_tcu = new stdClass();

        $device_configuration_plc->ip = $request->device_configuration_form['plc_ip'];
        $device_configuration_plc->modbus_tcp_port = $request->device_configuration_form['plc_modbus_tcp_port'];
        $device_configuration_plc->serial_num = $request->device_configuration_form['plc_serial_number'];

        $device_configuration_tcu->serial_num = $request->device_configuration_form['tcu_serial_number'];
        $device_configuration_tcu->port = $request->device_configuration_form['tcu_port'];
        $device_configuration_tcu->base_addr = $request->device_configuration_form['tcu_base_addr'];
        $device_configuration_tcu->baud = $request->device_configuration_form['tcu_baud'];
        $device_configuration_tcu->parity = $request->device_configuration_form['tcu_parity'];
        $device_configuration_tcu->data_bits = $request->device_configuration_form['tcu_data_bits'];
        $device_configuration_tcu->stop_bits = $request->device_configuration_form['tcu_stop_bits'];
        $device_configuration_tcu->byte_timeout = $request->device_configuration_form['tcu_bype_timeout'];
        $device_configuration_tcu->resp_timeout = $request->device_configuration_form['tcu_resp_timeout'];

        $device_configuration->batch_size = 4000;
        $device_configuration->batch_timeout = 60;
        $device_configuration->cmd = 'daemon_config';

        if (!$request->device_configuration_form['tcuAdded'])
            $device_configuration_tcu->port = '';

        $device_configuration->plc = $device_configuration_plc;
        $device_configuration->true_temp = $device_configuration_tcu;

        $req = [
            "targetDevice" => $device->serial_number,
            "requestJson" => $device_configuration
        ];

        $client = new Client();

        try {
            $response = $client->post(
                config('app.acs_middleware_url'),
                [
                    'json' => $req
                ]
            );

            return response()->json('Configuration successfully sent.');
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }

    public function suspendSIM($iccid)
    {
        $device = Device::where('iccid', $iccid)->first();

        if (!$device) {
            return response()->json('Device Not Found', 404);
        }

        $client = new Client();
        try {
            $response = $client->post(
                $this->suspendURL,
                [
                    'headers' => ['Content-type' => 'application/json'],
                    'auth' => [
                        'ACSGroup_API',
                        'HBSMYJM2'
                    ],
                    'json' => [
                        "deviceNumber" => $device->iccid,
                    ]
                ]
            );

            return $response->getBody();
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }

    public function remoteWeb($deviceid)
    {
        $device = Device::where('device_id', $deviceid)->first();
        if (!$device) {
            return response()->json('Device Not Found', 404);
        }
        $postControl = 'https://rms.teltonika-networks.com/api/devices/' . $deviceid . '/connect/webui';
        $getLink = 'https://rms.teltonika-networks.com/api/devices/' . $deviceid . '/links';

        $client = new Client();

        try {
            while (1) {
                $res = $client->post(
                    $postControl,
                    [
                        'headers' => [
                            'Authorization' => "Bearer " . $this->bearer_token
                        ],
                        'json' => [
                            "duration" => 400
                        ]
                    ]
                );


                if ($res) {
                    $response = $client->get(
                        $getLink,
                        [
                            'headers' => [
                                'Authorization' => "Bearer " . $this->bearer_token
                            ],
                            'json' => [
                                "type" => "webui"
                            ],
                        ]
                    );
                    $data = json_decode($response->getBody()->getContents())->data;

                    if (count($data))
                        return response()->json($data);
                }
            }
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }

    public function remoteCli($deviceid)
    {
        $device = Device::where('device_id', $deviceid)->first();
        if (!$device) {
            return response()->json('Device Not Found', 404);
        }
        $postControl = 'https://rms.teltonika-networks.com/api/devices/' . $deviceid . '/connect/cli';
        $getLink = 'https://rms.teltonika-networks.com/api/devices/' . $deviceid . '/links';

        $client = new Client();

        try {
            while (1) {
                $res = $client->post(
                    $postControl,
                    [
                        'headers' => [
                            'Authorization' => "Bearer " . $this->bearer_token
                        ],
                        'json' => [
                            "duration" => 400
                        ],
                    ]
                );
                if ($res) {
                    $response = $client->get(
                        $getLink,
                        [
                            'headers' => [
                                'Authorization' => "Bearer " . $this->bearer_token
                            ],
                            'json' => [
                                "type" => "cli"
                            ],
                        ]
                    );

                    $data = json_decode($response->getBody()->getContents())->data;

                    if (count($data))
                        return response()->json($data);
                }
            }
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }

    public function querySIM($iccid)
    {
        if (!$iccid) {
            return response()->json('Invalid ICCID', 404);
        }

        $device = Device::where('iccid', $iccid)->first();

        if (!$device) {
            return response()->json('Device Not Found', 404);
        }

        $client = new Client();
        try {
            $response = $client->post(
                $this->queryURL,
                [
                    'headers' => ['Content-type' => 'application/json'],
                    'auth' => [
                        'ACSGroup_API',
                        'HBSMYJM2'
                    ],
                    'json' => [
                        "deviceNumber" => $iccid,
                    ],

                ]
            );

            $device->sim_status = json_decode($response->getBody())->d->status;
            $device->save();

            return $device;
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }

    public function publicIP($iccid)
    {
        if (!$iccid) {
            return response()->json('Invalid ICCID', 404);
        }

        $device = Device::where('iccid', $iccid)->first();

        if (!$device) {
            return response()->json('Device Not Found', 404);
        }

        $client = new Client();
        try {
            $response = $client->post(
                $this->queryURL,
                [
                    'headers' => ['Content-type' => 'application/json'],
                    'auth' => [
                        'ACSGroup_API',
                        'HBSMYJM2'
                    ],
                    'json' => [
                        "deviceNumber" => $iccid,
                    ],
                ]
            );

            $device->public_ip_sim = json_decode($response->getBody())->d->staticIP;
            $device->save();

            return $device;
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }

    public function carrierFromKoreAPI($iccid)
    {
        if (!$iccid) {
            return response()->json('Invalid ICCID', 404);
        }

        $device = Device::where('iccid', $iccid)->first();

        if (!$device) {
            return response()->json('Device Not Found', 404);
        }

        $client = new Client();
        try {
            $response = $client->post(
                $this->queryURL,
                [
                    'headers' => ['Content-type' => 'application/json'],
                    'auth' => [
                        'ACSGroup_API',
                        'HBSMYJM2'
                    ],
                    'json' => [
                        "deviceNumber" => $iccid,
                    ],
                ]
            );
            $features = json_decode($response->getBody())->d->lstFeatures;
            foreach ($features as $key => $feature) {
                if (strpos($feature, 'FEAT015100') !== false) {
                    $feature = str_replace("FEAT015100: ", "", $feature);

                    if (strpos($feature, 'KTUSA') !== false) {
                        $device->carrier = str_replace("KTUSA", "T-Mobile", $feature);
                    } else if (strpos($feature, 'KUSG') !== false) {
                        $device->carrier = str_replace("KUSG", "AT&T", $feature);
                    } else if (strpos($feature, 'VZWLTE') !== false) {
                        $device->carrier = str_replace("VZWLTE", "Verizon", $feature);
                    }
                    break;
                }
            }

            $device->save();

            return $device;
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }

    public function getCustomerDevices(Request $request)
    {
        $user = $request->user('api');
        $devices = $user->company->devices;

        return response()->json([
            'devices' => $devices
        ]);
    }

    /*
        Get devices with analytics
    */
    public function getDevicesAnalytics(Request $request)
    {
        $user = $request->user('api');
        $location = $request->location_id;
        $page = $request->page;
        $itemsPerPage = $request->itemsPerPage;

        $query = null;

        if ($request->company_id == 0) {
            if ($user->hasRole(['acs_admin', 'acs_manager', 'acs_viewer'])) {
                if ($location) {
                    $query = Device::where('location_id', $location)->orderBy('sim_status')->orderBy('id');
                } else
                    $query = Device::orderBy('sim_status')->orderBy('id');
            } else {
                if ($location) {
                    $query = $user->company->devices()->where('location_id', $location)->orderBy('sim_status')->orderBy('id');
                } else
                    $query = $user->company->devices()->orderBy('sim_status')->orderBy('id');
            }
        } else {
            $company = Company::where('id', $request->company_id)->first();

            if ($location) {
                $query = $company->devices()->where('location_id', $location)->orderBy('sim_status')->orderBy('id');
            } else {
                $query = $company->devices()->orderBy('sim_status')->orderBy('id');
            }
        }

        $query->with(['teltonikaConfiguration', 'configuration:id,name']);
        $devices = $query->paginate($itemsPerPage, ['*'], 'page', $page);
        foreach ($devices as $key => $device) {
            $runningStatus = [];

            if ($device->teltonikaConfiguration) {
                $plcLinkStatus = $device->teltonikaConfiguration->plc_status ? true : false;
                $plcStatus = $device->teltonikaConfiguration->router_status ? true : false;
            } else {
                $plcLinkStatus = false;
                $plcStatus = false;
            }

            // $plcStatus = $this->getPlcStatus($device->device_id);
            $isRunning = $this->isMachineRunning($device->serial_number, $device->machine_id);
            $isIdle = $this->isMachineIdle($device->serial_number, $device->machine_id);
            $isActivePlcAlarm = $this->isPlcAlarmActivated($device->serial_number, $device->machine_id);
            $isThresholdActivated = $this->isThresholdsActivated($device->device_id, $device->machine_id, $user->id);
            $isApproachingActivated = $this->isApproachingActivated($device->device_id, $device->machine_id, $user->id);

            if (!$plcStatus) {
                array_push($runningStatus, 'routerNotConnected');
            } else {
                if (!$plcLinkStatus) {
                    array_push($runningStatus, 'plcNotConnected');
                } else {
                    if (!$isRunning) {
                        if ($isActivePlcAlarm) array_push($runningStatus, 'machineStoppedActiveAlarm');
                        else array_push($runningStatus, 'machineStopped');
                    } else {
                        if ($isIdle) array_push($runningStatus, 'machineIdle');
                        else {
                            if ($isActivePlcAlarm) array_push($runningStatus, 'machineRunningAlert');
                            else {
                                if ($isThresholdActivated || $isApproachingActivated) {
                                    if ($isThresholdActivated) array_push($runningStatus, 'machineRunningThreshold');
                                    else array_push($runningStatus, 'machineRunningAlert');
                                } else array_push($runningStatus, 'machineRunning');
                            }
                        }
                    }
                }
            }

            $device->status = $runningStatus;

            $start = strtotime("-8 Hours");
            $end = time();

            $downtime_by_type = $this->getDowntimeByTypeForMachine($device->serial_number, $start, $end);
            $capacity_utilization = $this->getCapacityUtilizationForMachine($device->serial_number, $device->machine_id);

            $device->downtimeByType = $downtime_by_type;
            $device->capacityUtilization = $capacity_utilization;
            $device->machineType = isset($device->configuration) ? $device->configuration['name'] : '';
        }

        return response()->json(compact('devices'));
    }

    public function getSavedMachines(Request $request)
    {
        $user = $request->user('api');
        $page = $request->page;
        $itemsPerPage = $request->itemsPerPage;

        $query = null;

        $query = Device::join('saved_machines', 'saved_machines.device_id', '=', 'devices.id')
            ->where('saved_machines.user_id', $user->id)
            ->select('devices.*')->orderBy('sim_status')->orderBy('id');
        $query->with(['teltonikaConfiguration', 'configuration:id,name']);
        $devices = $query->paginate($itemsPerPage, ['*'], 'page', $page);

        foreach ($devices as $key => $device) {
            $runningStatus = [];

            if ($device->teltonikaConfiguration) {
                $plcLinkStatus = $device->teltonikaConfiguration->plc_status ? true : false;
                $plcStatus = $device->teltonikaConfiguration->router_status ? true : false;
            } else {
                $plcLinkStatus = false;
                $plcStatus = false;
            }

            // $plcStatus = $this->getPlcStatus($device->device_id);
            $isRunning = $this->isMachineRunning($device->serial_number, $device->machine_id);
            $isIdle = $this->isMachineIdle($device->serial_number, $device->machine_id);
            $isActivePlcAlarm = $this->isPlcAlarmActivated($device->serial_number, $device->machine_id);
            $isThresholdActivated = $this->isThresholdsActivated($device->device_id, $device->machine_id, $user->id);
            $isApproachingActivated = $this->isApproachingActivated($device->device_id, $device->machine_id, $user->id);

            if (!$plcStatus) {
                array_push($runningStatus, 'routerNotConnected');
            } else {
                if (!$plcLinkStatus) {
                    array_push($runningStatus, 'plcNotConnected');
                } else {
                    if (!$isRunning) {
                        if ($isActivePlcAlarm) array_push($runningStatus, 'machineStoppedActiveAlarm');
                        else array_push($runningStatus, 'machineStopped');
                    } else {
                        if ($isIdle) array_push($runningStatus, 'machineIdle');
                        else {
                            if ($isActivePlcAlarm) array_push($runningStatus, 'machineRunningAlert');
                            else {
                                if ($isThresholdActivated || $isApproachingActivated) {
                                    if ($isThresholdActivated) array_push($runningStatus, 'machineRunningThreshold');
                                    else array_push($runningStatus, 'machineRunningAlert');
                                } else array_push($runningStatus, 'machineRunning');
                            }
                        }
                    }
                }
            }

            $device->status = $runningStatus;
            $device->downtimeByType = $this->getDowntimeByTypeForMachine($device->serial_number);
            $capacity_utilization = $this->getCapacityUtilizationForMachine($device->serial_number, $device->machine_id);
            $device->capacityUtilization = $capacity_utilization;
            $device->machineType = isset($device->configuration) ? $device->configuration['name'] : '';
        }

        return response()->json(compact('devices'));
    }

    public function getDashboardMachinesTable(Request $request)
    {
        $user = $request->user('api');

        $location = $request->location;
        $zone = $request->zone;
        $page = $request->page;

        if ($user->hasRole(['acs_admin', 'acs_manager', 'acs_viewer'])) {
            $query = Device::where('location_id', $location)->where('zone_id', $zone);
        } else {
            $query = $user->company->devices()->where('location_id', $location)->where('zone_id', $zone);
        }

        $query->with('teltonikaConfiguration', 'configuration:id,name');
        $devices = $query->paginate($request->itemsPerPage, ['*'], 'page', $page);

        foreach ($devices as $key => $device) {
            $runningStatus = [];

            if ($device->teltonikaConfiguration) {
                $plcLinkStatus = $device->teltonikaConfiguration->plc_status ? true : false;
                $plcStatus = $device->teltonikaConfiguration->router_status ? true : false;
            } else {
                $plcLinkStatus = false;
                $plcStatus = false;
            }

            // $plcStatus = $this->getPlcStatus($device->device_id);
            $isRunning = $this->isMachineRunning($device->serial_number, $device->machine_id);
            $isIdle = $this->isMachineIdle($device->serial_number, $device->machine_id);
            $isActivePlcAlarm = $this->isPlcAlarmActivated($device->serial_number, $device->machine_id);
            $isThresholdActivated = $this->isThresholdsActivated($device->device_id, $device->machine_id, $user->id);
            $isApproachingActivated = $this->isApproachingActivated($device->device_id, $device->machine_id, $user->id);

            if (!$plcStatus) {
                array_push($runningStatus, 'routerNotConnected');
            } else {
                if (!$plcLinkStatus) {
                    array_push($runningStatus, 'plcNotConnected');
                } else {
                    if (!$isRunning) {
                        if ($isActivePlcAlarm) array_push($runningStatus, 'machineStoppedActiveAlarm');
                        else array_push($runningStatus, 'machineStopped');
                    } else {
                        if ($isIdle) array_push($runningStatus, 'machineIdle');
                        else {
                            if ($isActivePlcAlarm) array_push($runningStatus, 'machineRunningAlert');
                            else {
                                if ($isThresholdActivated || $isApproachingActivated) {
                                    if ($isThresholdActivated) array_push($runningStatus, 'machineRunningThreshold');
                                    else array_push($runningStatus, 'machineRunningAlert');
                                } else array_push($runningStatus, 'machineRunning');
                            }
                        }
                    }
                }
            }

            $device->status = $runningStatus;

            $downtime_by_reason = $this->getMachineDowntime($device->serial_number);
            $capacity_utilization = $this->getCapacityUtilizationForMachine($device->serial_number, $device->machine_id);
            $device->capacityUtilization = $capacity_utilization;
            $device->downtimeByReason = $downtime_by_reason;
            $device->machineType = isset($device->configuration) ? $device->configuration['name'] : '';
        }

        return response()->json(compact('devices'));
    }

    public function getDowntimeGraphData(Request $request)
    {
        $user = $request->user('api');

        $timeFrom = $request->from / 1000;
        $timeTo = $request->to / 1000;

        $total_downtime = 0;
        $average_downtime = new stdClass();
        $average_downtime->name = 'Average Downtime';
        $average_downtime->type = 'line';
        $average_downtime->data = [];
        $dates = [];

        $devices = null;
        $location = $request->location_id;
        $zone = $request->zone_id;

        /**
         * Pass Query Filters here to get the data based on
         * location zone serial number and device id
         *
         */
        if ($request->company_id == 0) {
            $devices = app(Pipeline::class)
                ->send($user->getMyDevicesForProductData($location, $zone))
                ->through([
                    MachineFilter::class
                ])
                ->thenReturn()->pluck('serial_number')->toArray();
        } else {
            $customer_admin_role = Role::findOrFail(ROLE_CUSTOMER_ADMIN);
            $customer_admin = $customer_admin_role->users->where('company_id', $request->company_id)->first();
            $devices = app(Pipeline::class)
                ->send($customer_admin->getMyDevicesForProductData($location, $zone))
                ->through([
                    MachineFilter::class
                ])
                ->thenReturn()->pluck('serial_number')->toArray();
        }


        /* End of query filters */

        $ids = implode(", ", $devices);

        if (!$devices) {
            $ids = 0;
        }

        $query = "select
                aggregated_subquery.reason_name as name,
                json_agg(ROUND(aggregated_subquery.hours_sum::numeric, 3) order by aggregated_subquery.output_date_int) as data
            from (
                select
                    detailed_subquery.reason_name as reason_name,
                    detailed_subquery.output_date_int as output_date_int,
                    coalesce(sum(detailed_subquery.corrected_downtime_end_int - detailed_subquery.corrected_downtime_start_int)/(60*60), 0) as hours_sum
                from (
                    with
                    input_params as (
                        select
                            $timeFrom as start_datetime,
                            $timeTo as end_datetime
                    ),
                    datetime_config as (
                        select 60*60*24 as day_duration
                    ),
                    output_dates as (
                        select generate_series(
                            date_trunc('day', to_timestamp(input_params.start_datetime)),
                            case when
                                extract(hour from to_timestamp(input_params.end_datetime)) = 0
                                and extract(minute from to_timestamp(input_params.end_datetime)) = 0
                                and extract(second from to_timestamp(input_params.end_datetime)) = 0
                            then
                                date_trunc('day', to_timestamp(input_params.end_datetime - datetime_config.day_duration))
                            else
                                to_timestamp(input_params.end_datetime)
                            end,
                            interval '1 day'
                        ) as generated_date
                        from input_params, datetime_config
                    ),
                    output_dates_int as (
                        select
                            extract(epoch from generated_date) as date
                        from output_dates
                    )

                    select
                        output_dates_int.date as output_date_int,
                        downtime_reasons.name as reason_name,

                        case when downtimes.start_time is not null then
                            greatest(input_params.start_datetime, output_dates_int.date, downtimes.start_time)
                        else
                            null
                        end as corrected_downtime_start_int,

                        case when downtimes.start_time is not null then
                            least(input_params.end_datetime, output_dates_int.date + datetime_config.day_duration, downtimes.end_time)
                        else
                            null
                        end as corrected_downtime_end_int

                    from input_params
                    left join output_dates_int on 0 = 0
                    left join downtime_reasons on 0 = 0
                    left join datetime_config on 0 = 0
                    left join downtimes on downtimes.reason_id = downtime_reasons.id
                        and downtimes.start_time <= least(output_dates_int.date + datetime_config.day_duration, input_params.end_datetime)
                        and downtimes.end_time >= greatest(output_dates_int.date, input_params.start_datetime)
                        and downtimes.device_id in ($ids)
                    order by output_dates_int.date, downtime_reasons.name, downtimes.start_time

                ) as detailed_subquery
                group by detailed_subquery.reason_name, detailed_subquery.output_date_int

            ) as aggregated_subquery
            group by aggregated_subquery.reason_name";

        $date_generate_query = "select generate_series(
            date_trunc('day', to_timestamp($timeFrom)),
            case when
                extract(hour from to_timestamp($timeTo)) = 0
                and extract(minute from to_timestamp($timeTo)) = 0
                and extract(second from to_timestamp($timeTo)) = 0
            then
                date_trunc('day', to_timestamp($timeTo - 60*60*24))
            else
                to_timestamp($timeTo)
            end,
            interval '1 day'
            )::date as generated_date";

        $series = DB::select($query);
        $generated_dates = DB::select($date_generate_query);

        foreach ($series as $data) {
            $data->data = json_decode($data->data);
            $data->type = 'column';
            $total_downtime += array_sum($data->data);
        };

        $availability_target = new stdClass();
        $availability_target->name = 'Target Availability';
        $availability_target->data = [];

        $availability_actual = new stdClass();
        $availability_actual->name = 'Actual Availability';
        $availability_actual->data = [];

        $availability_series = [];

        foreach ($generated_dates as $key => $date) {
            array_push($dates, $date->generated_date);
            array_push($average_downtime->data, round($total_downtime / count($generated_dates), 3));
            $target = AvailabilityPlanTime::where('timestamp', '<=', strtotime($date->generated_date))->orderBy('timestamp', 'DESC')->first();
            if ($target) {
                array_push($availability_target->data, round($target->hours / 24, 3));
                $actual = 0;
                foreach ($series as $data) {
                    $actual += $data->data[$key];
                }
                array_push($availability_actual->data, round(($target->hours - $actual) / $target->hours, 3));
            } else {
                array_push($availability_target->data, round(16 / 24, 3));
                $actual = 0;
                foreach ($series as $data) {
                    $actual += $data->data[$key];
                }
                array_push($availability_actual->data, round((16 - $actual) / 16, 3));
            }
        };

        array_push($series, $average_downtime);
        array_push($availability_series, $availability_target);
        array_push($availability_series, $availability_actual);

        return response()->json(compact('series', 'dates', 'availability_series'));
    }

    public function getDowntimeByTypeGraphData(Request $request)
    {

        $user = $request->user('api');

        $timeFrom = $request->from / 1000;
        $timeTo = $request->to / 1000;

        $location = $request->location_id;
        $zone = $request->zone_id;

        /**
         * Pass Query Filters here to get the data based on
         * location zone serial number and device id
         *
         */

        if ($request->company_id == 0) {

            $devices = app(Pipeline::class)
                ->send($user->getMyDevicesForProductData($location, $zone))
                ->through([
                    MachineFilter::class
                ])
                ->thenReturn()->pluck('serial_number')->toArray();
        } else {
            $customer_admin_role = Role::findOrFail(ROLE_CUSTOMER_ADMIN);
            $customer_admin = $customer_admin_role->users->where('company_id', $request->company_id)->first();
            $devices = app(Pipeline::class)
                ->send($customer_admin->getMyDevicesForProductData($location, $zone))
                ->through([
                    MachineFilter::class
                ])
                ->thenReturn()->pluck('serial_number')->toArray();
        }

        $ids = implode(", ", $devices);

        if (!$devices) {
            $ids = 0;
        }

        $query = "select
                overall_subquery.type_name as name,
                ROUND(sum(overall_subquery.hours_sum)::numeric, 3) as data
            from (
                select
                    detailed_subquery.type_name as type_name,
                    detailed_subquery.output_date_int as output_date_int,
                    coalesce(sum(detailed_subquery.corrected_downtime_end_int - detailed_subquery.corrected_downtime_start_int)/(60*60), 0) as hours_sum
                from (
                    with
                    input_params as (
                        select
                            $timeFrom as start_datetime,
                            $timeTo as end_datetime
                    ),
                    datetime_config as (
                        select 60*60*24 as day_duration
                    ),
                    output_dates as (
                        select generate_series(
                            date_trunc('day', to_timestamp(input_params.start_datetime)),
                            case when
                                extract(hour from to_timestamp(input_params.end_datetime)) = 0
                                and extract(minute from to_timestamp(input_params.end_datetime)) = 0
                                and extract(second from to_timestamp(input_params.end_datetime)) = 0
                            then
                                date_trunc('day', to_timestamp(input_params.end_datetime - datetime_config.day_duration))
                            else
                                to_timestamp(input_params.end_datetime)
                            end,
                            interval '1 day'
                        ) as generated_date
                        from input_params, datetime_config
                    ),
                    output_dates_int as (
                        select
                            extract(epoch from generated_date) as date
                        from output_dates
                    )

                    select
                        output_dates_int.date as output_date_int,
                        downtime_type.name as type_name,

                        case when downtimes.start_time is not null then
                            greatest(input_params.start_datetime, output_dates_int.date, downtimes.start_time)
                        else
                            null
                        end as corrected_downtime_start_int,

                        case when downtimes.start_time is not null then
                            least(input_params.end_datetime, output_dates_int.date + datetime_config.day_duration, downtimes.end_time)
                        else
                            null
                        end as corrected_downtime_end_int

                    from input_params
                    left join output_dates_int on 0 = 0
                    left join downtime_type on 0 = 0
                    left join datetime_config on 0 = 0
                    left join downtimes on downtimes.type = downtime_type.id
                        and downtimes.start_time <= least(output_dates_int.date + datetime_config.day_duration, input_params.end_datetime)
                        and downtimes.end_time >= greatest(output_dates_int.date, input_params.start_datetime)
                        and downtimes.device_id in ($ids)
                    order by output_dates_int.date, downtime_type.name, downtimes.start_time

                ) as detailed_subquery
                group by detailed_subquery.type_name, detailed_subquery.output_date_int
            ) as overall_subquery
            group by overall_subquery.type_name
            order by data desc";

        $series = DB::select($query);

        foreach ($series as $data) {
            $data->data = json_decode($data->data);
        };

        return response()->json(compact('series'));
    }

    public function getDowntimeByReasonGraphData(Request $request)
    {
        $user = $request->user('api');

        $timeFrom = $request->from / 1000;
        $timeTo = $request->to / 1000;

        $devices = null;
        $location = $request->location_id;
        $zone = $request->zone_id;

        /**
         * Pass Query Filters here to get the data based on
         * location zone serial number and device id
         *
         */

        if ($request->company_id == 0) {
            $devices = app(Pipeline::class)
                ->send($user->getMyDevicesForProductData($location, $zone))
                ->through([
                    MachineFilter::class
                ])
                ->thenReturn()->pluck('serial_number')->toArray();
        } else {
            $customer_admin_role = Role::findOrFail(ROLE_CUSTOMER_ADMIN);
            $customer_admin = $customer_admin_role->users->where('company_id', $request->company_id)->first();
            $devices = app(Pipeline::class)
                ->send($customer_admin->getMyDevicesForProductData($location, $zone))
                ->through([
                    MachineFilter::class
                ])
                ->thenReturn()->pluck('serial_number')->toArray();
        }

        $ids = implode(", ", $devices);

        if (!$devices) {
            $ids = 0;
        }

        $query = "select
                overall_subquery.reason_name as name,
                ROUND(sum(hours_sum)::numeric, 3) as data
            from (
                select
                    detailed_subquery.reason_name as reason_name,
                    detailed_subquery.output_date_int as output_date_int,
                    coalesce(sum(detailed_subquery.corrected_downtime_end_int - detailed_subquery.corrected_downtime_start_int)/(60*60), 0) as hours_sum
                from (
                    with
                    input_params as (
                        select
                            $timeFrom as start_datetime,
                            $timeTo as end_datetime
                    ),
                    datetime_config as (
                        select 60*60*24 as day_duration
                    ),
                    output_dates as (
                        select generate_series(
                            date_trunc('day', to_timestamp(input_params.start_datetime)),
                            case when
                                extract(hour from to_timestamp(input_params.end_datetime)) = 0
                                and extract(minute from to_timestamp(input_params.end_datetime)) = 0
                                and extract(second from to_timestamp(input_params.end_datetime)) = 0
                            then
                                date_trunc('day', to_timestamp(input_params.end_datetime - datetime_config.day_duration))
                            else
                                to_timestamp(input_params.end_datetime)
                            end,
                            interval '1 day'
                        ) as generated_date
                        from input_params, datetime_config
                    ),
                    output_dates_int as (
                        select
                            extract(epoch from generated_date) as date
                        from output_dates
                    )

                    select
                        output_dates_int.date as output_date_int,
                        downtime_reasons.name as reason_name,

                        case when downtimes.start_time is not null then
                            greatest(input_params.start_datetime, output_dates_int.date, downtimes.start_time)
                        else
                            null
                        end as corrected_downtime_start_int,

                        case when downtimes.start_time is not null then
                            least(input_params.end_datetime, output_dates_int.date + datetime_config.day_duration, downtimes.end_time)
                        else
                            null
                        end as corrected_downtime_end_int

                    from input_params
                    left join output_dates_int on 0 = 0
                    left join downtime_reasons on 0 = 0
                    left join datetime_config on 0 = 0
                    left join downtimes on downtimes.reason_id = downtime_reasons.id
                        and downtimes.start_time <= least(output_dates_int.date + datetime_config.day_duration, input_params.end_datetime)
                        and downtimes.end_time >= greatest(output_dates_int.date, input_params.start_datetime)
                        and downtimes.device_id in ($ids)
                    order by output_dates_int.date, downtime_reasons.name, downtimes.start_time

                ) as detailed_subquery
                group by detailed_subquery.reason_name, detailed_subquery.output_date_int
            ) as overall_subquery
            group by overall_subquery.reason_name
            order by data desc";

        $series = DB::select($query);

        foreach ($series as $data) {
            $data->data = json_decode($data->data);
        };

        return response()->json(compact('series'));
    }

    public function getDowntimeTableData(Request $request)
    {
        $user = $request->user('api');

        $device_ids = $user->company->devices->pluck('serial_number');
        $downtimeTypes = DowntimeType::get();
        $locations = Location::get();
        $zones = Zone::get();
        $reasons = DowntimeReason::get();

        //Using pipelines and query filters for sorting
        $downtimes = app(Pipeline::class)
            ->send(Downtimes::whereIn('downtimes.device_id', $device_ids)
                ->join('devices', 'devices.serial_number', '=', 'downtimes.device_id')
                ->join('machines', 'devices.machine_id', '=', 'machines.id')
                ->join('locations', 'locations.id', '=', 'devices.location_id')
                ->join('downtime_type', 'downtimes.type', '=', 'downtime_type.id')
                ->join('zones', 'locations.id', '=', 'zones.location_id')
                ->join('downtime_reasons', 'downtimes.reason_id', '=', 'downtime_reasons.id')
                ->select('downtimes.*', 'downtimes.device_id as serial_number', 'machines.name as machine_name',
                    'locations.name as location_name', 'downtime_type.name as downtime_type_name',
                    'zones.name as zone_name', 'downtime_reasons.name as downtime_reason', 'locations.id as location_id',
                    'zones.id as zone_id', 'devices.machine_id as machine_id', 'devices.serial_number as device_serial_number'
                ))
            ->through([
                DowntimeFilter::class,
                Sort::class,
                MachineFilter::class
            ])
            ->thenReturn();

        $min_max = DB::table('downtimes')->whereIn('downtimes.device_id', $device_ids)
            ->select(DB::raw('MIN(start_time) as start_value'),DB::raw('MAX(end_time) as end_value'))
            ->first();

        $downtimes = (new DownTimeTableDataResource($downtimes->paginate(request()->has('items') ? request('items') : 10)))->toArray($request);

        $downtimes = array_merge($downtimes,[
            'min_max' => $min_max
        ]);

        return response()->json(compact('downtimes', 'downtimeTypes', 'locations', 'zones', 'reasons'));
    }

    public function updateDowntime(Request $request)
    {
        $downtime = Downtimes::where('id', $request->id)->first();

        try {
            $downtime->update([
                'reason_id' => $request->reason['id'],
                'type' => $request->type['id'],
                'comment' => $request->comment
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Updated successfully'
            ]);
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to update downtime'
            ]);
        }
    }

    public function getDowntimeByTypeForMachine($device_id, $start = 0, $end = 0)
    {
        if (!$start) $start = strtotime("-8 Hours");
        if (!$end) $end = time();

        $query = "select
                overall_subquery.type_name as name,
                ROUND(sum(overall_subquery.hours_sum)::numeric, 3) as data
            from (
                select
                    detailed_subquery.type_name as type_name,
                    detailed_subquery.output_date_int as output_date_int,
                    coalesce(sum(detailed_subquery.corrected_downtime_end_int - detailed_subquery.corrected_downtime_start_int)/(60*60), 0) as hours_sum
                from (
                    with
                    input_params as (
                        select
                            $start as start_datetime,
                            $end as end_datetime
                    ),
                    datetime_config as (
                        select 60*60*24 as day_duration
                    ),
                    output_dates as (
                        select generate_series(
                            date_trunc('day', to_timestamp(input_params.start_datetime)),
                            case when
                                extract(hour from to_timestamp(input_params.end_datetime)) = 0
                                and extract(minute from to_timestamp(input_params.end_datetime)) = 0
                                and extract(second from to_timestamp(input_params.end_datetime)) = 0
                            then
                                date_trunc('day', to_timestamp(input_params.end_datetime - datetime_config.day_duration))
                            else
                                to_timestamp(input_params.end_datetime)
                            end,
                            interval '1 day'
                        ) as generated_date
                        from input_params, datetime_config
                    ),
                    output_dates_int as (
                        select
                            extract(epoch from generated_date) as date
                        from output_dates
                    )

                    select
                        output_dates_int.date as output_date_int,
                        downtime_type.name as type_name,

                        case when downtimes.start_time is not null then
                            greatest(input_params.start_datetime, output_dates_int.date, downtimes.start_time)
                        else
                            null
                        end as corrected_downtime_start_int,

                        case when downtimes.start_time is not null then
                            least(input_params.end_datetime, output_dates_int.date + datetime_config.day_duration, downtimes.end_time)
                        else
                            null
                        end as corrected_downtime_end_int

                    from input_params
                    left join output_dates_int on 0 = 0
                    left join downtime_type on 0 = 0
                    left join datetime_config on 0 = 0
                    left join downtimes on downtimes.type = downtime_type.id
                        and downtimes.start_time <= least(output_dates_int.date + datetime_config.day_duration, input_params.end_datetime)
                        and downtimes.end_time >= greatest(output_dates_int.date, input_params.start_datetime)
                        and downtimes.device_id = $device_id
                    order by output_dates_int.date, downtime_type.name, downtimes.start_time

                ) as detailed_subquery
                group by detailed_subquery.type_name, detailed_subquery.output_date_int
            ) as overall_subquery
            group by overall_subquery.type_name
            order by data desc";

        $series = DB::select($query);

        foreach ($series as $data) {
            $data->data = json_decode($data->data);
        };

        return $series;
    }

    public function setAvailabilityPlanTime(Request $request)
    {
        $user = $request->user('api');
        $time = $request->date / 1000;

        try {
            $plan = AvailabilityPlanTime::where('timestamp', $time)->where('company_id', $user->company->id)->first();

            if ($plan) {
                $plan->update([
                    'timestamp' => $time,
                    'hours' => $request->time
                ]);
            } else {
                AvailabilityPlanTime::create([
                    'timestamp' => $time,
                    'company_id' => $user->company->id,
                    'hours' => $request->time
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Availability plan time has been set successfully'
            ]);

        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to set plan time'
            ]);
        };
    }

    /**
     * Get machine's downtime by reason
     *
     * @param $id        integer
     * @param $start    timestamp
     * @param $end        timestamp
     * @return            object
     */
    public function getMachineDowntime($id, $start = 0, $end = 0)
    {
        if (!$start) $start = strtotime("-8 Hours");
        if (!$end) $end = time();

        $query = "select
                overall_subquery.type_name as name,
                ROUND(sum(overall_subquery.hours_sum)::numeric, 3) as data
            from (
                select
                    detailed_subquery.type_name as type_name,
                    detailed_subquery.output_date_int as output_date_int,
                    coalesce(sum(detailed_subquery.corrected_downtime_end_int - detailed_subquery.corrected_downtime_start_int)/(60*60), 0) as hours_sum
                from (
                    with
                    input_params as (
                        select
                            $start as start_datetime,
                            $end as end_datetime
                    ),
                    datetime_config as (
                        select 60*60*24 as day_duration
                    ),
                    output_dates as (
                        select generate_series(
                            date_trunc('day', to_timestamp(input_params.start_datetime)),
                            case when
                                extract(hour from to_timestamp(input_params.end_datetime)) = 0
                                and extract(minute from to_timestamp(input_params.end_datetime)) = 0
                                and extract(second from to_timestamp(input_params.end_datetime)) = 0
                            then
                                date_trunc('day', to_timestamp(input_params.end_datetime - datetime_config.day_duration))
                            else
                                to_timestamp(input_params.end_datetime)
                            end,
                            interval '1 day'
                        ) as generated_date
                        from input_params, datetime_config
                    ),
                    output_dates_int as (
                        select
                            extract(epoch from generated_date) as date
                        from output_dates
                    )

                    select
                        output_dates_int.date as output_date_int,
                        downtime_type.name as type_name,

                        case when downtimes.start_time is not null then
                            greatest(input_params.start_datetime, output_dates_int.date, downtimes.start_time)
                        else
                            null
                        end as corrected_downtime_start_int,

                        case when downtimes.start_time is not null then
                            least(input_params.end_datetime, output_dates_int.date + datetime_config.day_duration, downtimes.end_time)
                        else
                            null
                        end as corrected_downtime_end_int

                    from input_params
                    left join output_dates_int on 0 = 0
                    left join downtime_type on 0 = 0
                    left join datetime_config on 0 = 0
                    left join downtimes on downtimes.type = downtime_type.id
                        and downtimes.start_time <= least(output_dates_int.date + datetime_config.day_duration, input_params.end_datetime)
                        and downtimes.end_time >= greatest(output_dates_int.date, input_params.start_datetime)
                        and downtimes.device_id = $id
                    order by output_dates_int.date, downtime_type.name, downtimes.start_time

                ) as detailed_subquery
                group by detailed_subquery.type_name, detailed_subquery.output_date_int
            ) as overall_subquery
            group by overall_subquery.type_name
            order by data desc";

        $series = DB::select($query);

        foreach ($series as $data) {
            $data->data = json_decode($data->data);
        };

        return $series;
    }

    /**
     * Get downtime availability of machine
     *
     * @param $id        integer
     * @param $start    timestamp
     * @param $end        timestamp
     * @return            object
     */
    public function getMachineDowntimeAvailability($id, $start = 0, $end = 0)
    {
        if (!$start) $start = strtotime("-1 day");
        if (!$end) $end = time();

        $dates = [];

        $query = "select
                aggregated_subquery.reason_name as name,
                json_agg(ROUND(aggregated_subquery.hours_sum::numeric, 3) order by aggregated_subquery.output_date_int) as data
            from (
                select
                    detailed_subquery.reason_name as reason_name,
                    detailed_subquery.output_date_int as output_date_int,
                    coalesce(sum(detailed_subquery.corrected_downtime_end_int - detailed_subquery.corrected_downtime_start_int)/(60*60), 0) as hours_sum
                from (
                    with
                    input_params as (
                        select
                            $start as start_datetime,
                            $end as end_datetime
                    ),
                    datetime_config as (
                        select 60*60*24 as day_duration
                    ),
                    output_dates as (
                        select generate_series(
                            date_trunc('day', to_timestamp(input_params.start_datetime)),
                            case when
                                extract(hour from to_timestamp(input_params.end_datetime)) = 0
                                and extract(minute from to_timestamp(input_params.end_datetime)) = 0
                                and extract(second from to_timestamp(input_params.end_datetime)) = 0
                            then
                                date_trunc('day', to_timestamp(input_params.end_datetime - datetime_config.day_duration))
                            else
                                to_timestamp(input_params.end_datetime)
                            end,
                            interval '1 day'
                        ) as generated_date
                        from input_params, datetime_config
                    ),
                    output_dates_int as (
                        select
                            extract(epoch from generated_date) as date
                        from output_dates
                    )

                    select
                        output_dates_int.date as output_date_int,
                        downtime_reasons.name as reason_name,

                        case when downtimes.start_time is not null then
                            greatest(input_params.start_datetime, output_dates_int.date, downtimes.start_time)
                        else
                            null
                        end as corrected_downtime_start_int,

                        case when downtimes.start_time is not null then
                            least(input_params.end_datetime, output_dates_int.date + datetime_config.day_duration, downtimes.end_time)
                        else
                            null
                        end as corrected_downtime_end_int

                    from input_params
                    left join output_dates_int on 0 = 0
                    left join downtime_reasons on 0 = 0
                    left join datetime_config on 0 = 0
                    left join downtimes on downtimes.reason_id = downtime_reasons.id
                        and downtimes.start_time <= least(output_dates_int.date + datetime_config.day_duration, input_params.end_datetime)
                        and downtimes.end_time >= greatest(output_dates_int.date, input_params.start_datetime)
                        and downtimes.device_id = $id
                    order by output_dates_int.date, downtime_reasons.name, downtimes.start_time

                ) as detailed_subquery
                group by detailed_subquery.reason_name, detailed_subquery.output_date_int

            ) as aggregated_subquery
            group by aggregated_subquery.reason_name";

        $date_generate_query = "select generate_series(
			date_trunc('day', to_timestamp($start)),
			case when
				extract(hour from to_timestamp($end)) = 0
				and extract(minute from to_timestamp($end)) = 0
				and extract(second from to_timestamp($end)) = 0
			then
				date_trunc('day', to_timestamp($end - 60*60*24))
			else
				to_timestamp($end)
			end,
			interval '1 day'
			)::date as generated_date";

        $series = DB::select($query);
        $generated_dates = DB::select($date_generate_query);

        foreach ($series as $data) {
            $data->data = json_decode($data->data);
        };

        $availability_target = new stdClass();
        $availability_target->name = 'Target Availability';
        $availability_target->data = [];

        $availability_actual = new stdClass();
        $availability_actual->name = 'Actual Availability';
        $availability_actual->data = [];

        $availability_series = [];

        foreach ($generated_dates as $key => $date) {
            array_push($dates, $date->generated_date);
            $target = AvailabilityPlanTime::where('timestamp', '<=', strtotime($date->generated_date))->orderBy('timestamp', 'DESC')->first();
            if ($target) {
                array_push($availability_target->data, round($target->hours / 24, 3));
                $actual = 0;
                foreach ($series as $data) {
                    $actual += $data->data[$key];
                }
                array_push($availability_actual->data, round(($target->hours - $actual) / $target->hours, 3));
            } else {
                array_push($availability_target->data, round(16 / 24, 3));
                $actual = 0;
                foreach ($series as $data) {
                    $actual += $data->data[$key];
                }
                array_push($availability_actual->data, round((16 - $actual) / 16, 3));
            }
        };

        array_push($availability_series, $availability_target);
        array_push($availability_series, $availability_actual);

        return $availability_series;
    }

    public function getCapacityUtilizationForMachine($serial_number, $machine_id)
    {
        $from = strtotime("-8 Hours");
        $to = time();

        if ($machine_id == 11) {
            $utilizations_object = DB::table('utilizations')
                ->where('device_id', $serial_number)
                ->where('tag_id', 28)
                ->where('timestamp', '>', $from)
                ->where('timestamp', '<', $to)
                ->latest('timestamp')
                ->first();

            if (!$utilizations_object) {
                $utilizations_object = DB::table('utilizations')
                    ->where('device_id', $serial_number)
                    ->where('tag_id', 29)
                    ->where('timestamp', '>', $from)
                    ->where('timestamp', '<', $to)
                    ->latest('timestamp')
                    ->first();
            }

            $utilizations = $utilizations_object ? round(json_decode($utilizations_object->values)[0] / 10, 2) : null;

        } else {
            $tag_utilization = Tag::where('tag_name', 'capacity_utilization')->where('configuration_id', $machine_id)->first();

            if (!$tag_utilization) {
                return null;
            }

            $utilizations_object = DB::table('utilizations')
                ->where('device_id', $serial_number)
                ->where('tag_id', $tag_utilization->tag_id)
                ->where('timestamp', '>', $from)
                ->where('timestamp', '<', $to)
                ->latest('timestamp')
                ->first();

            $utilizations = $utilizations_object ? round(json_decode($utilizations_object->values)[0] / 10, 2) : null;

        }
        $item = $utilizations;

        return $item;
    }

    // update machines table default header option
    public function setMachinesTableDefaultHeader(Request $request)
    {
        $user = $request->user('api');
        $headers = $request['headers'];
        $user_customization = UserCustomizations::where('user_id', $user->id)->first();
        $headerOption = $this->getMachinesTableKeyName($request->name);

        if ($user_customization) {
            $customization = json_decode($user_customization->customization);
            $customization->$headerOption = $headers;

            $user_customization->update([
				'user_id' => $user->id,
				'customization' => json_encode($customization)
			]);
        } else {
            $options = new stdClass();
            $options->$headerOption = $headers;

            UserCustomizations::create([
				'user_id' => $user->id,
				'customization' => json_encode($options)
			]);
        }

        return response()->json(['User state has been updated.']);
    }

    // Update machines table sort options
    public function setMachinestableDefaultSortOptions(Request $request)
    {
        $user = $request->user('api');
        $user_customization = UserCustomizations::where('user_id', $user->id)->first();
        $headerOption = $this->getMachinesTableKeyName($request->name) . 'SortOption';

        if ($user_customization) {
            $customization = json_decode($user_customization->customization);
            $customization->$headerOption = $request->sortOption;

            $user_customization->update([
                'user_id' => $user->id,
                'customization' => json_encode($customization)
            ]);
        } else {
            $options = new stdClass();
            $options->$headerOption = $request->sortOption;

            UserCustomizations::create([
                'user_id' => $user->id,
				'customization' => json_encode($options)
            ]);
        }

        return response()->json(['User state has been updated.']);
    }

    // Set saved machines table headers
    public function setSavedMachinesTableDefaultHeader(Request $request)
    {
        $user = $request->user('api');
        $headers = $request['headers'];
        $user_customization = UserCustomizations::where('user_id', $user->id)->first();
        $headerOption = $this->getSavedMachinesTableKeyName($request->name);

        if ($user_customization) {
            $customization = json_decode($user_customization->customization);
            $customization->$headerOption = $headers;

            $user_customization->update([
				'user_id' => $user->id,
				'customization' => json_encode($customization)
			]);
        } else {
            $options = new stdClass();
            $options->$headerOption = $headers;

            UserCustomizations::create([
				'user_id' => $user->id,
				'customization' => json_encode($options)
			]);
        }

        return response()->json(['User state has been updated.']);
    }

    // Get machines table headers for user
    public function getMachinesTableHeaders(Request $request)
    {
        $user = $request->user('api');
        $customization = UserCustomizations::where('user_id', $user->id)->first();
        $pathName = $request->name;
        $headerOption = $this->getMachinesTableKeyName($request->name);
        $sortOption = $this->getMachinesTableKeyName($request->name) . 'SortOption';

        if ($customization) {
            $option = json_decode($customization->customization);
            if (isset($option->$headerOption)) {
                $headers = $option->$headerOption;
            } else {
                $headers = null;
            }
            if (isset($option->$sortOption)) {
                $sortOption = $option->$sortOption;
            } else {
                $sortOption = [
                    'sortBy' => ['zone_id'],
                    'sortDesc' => [true]
                ];
            }
        } else {
            $headers = null;
            $sortOption = [
                'sortBy' => ['zone_id'],
                'sortDesc' => [true]
            ];
        }

        return response()->json(compact('headers', 'sortOption', 'pathName'));
    }

    // Get saved machines table headers for users
    public function getSavedMachinesTableHeaders(Request $request)
    {
        $user = $request->user('api');
        $pathName = $request->name;
        $customization = UserCustomizations::where('user_id', $user->id)->first();
        $headerOption = $this->getSavedMachinesTableKeyName($request->name);

        if ($customization) {
            $option = json_decode($customization->customization);
            if (isset($option->$headerOption)) {
                $headers = $option->$headerOption;
            } else {
                $headers = null;
            }
        } else {
            $headers = null;
        }

        return response()->json(compact('headers', 'pathName'));
    }

    // Get customization option name
    public function getMachinesTableKeyName($name)
    {
        switch ($name) {
            case 'dashboard-analytics':
                return 'companyMachinesTableHeader';
                break;
            case 'location-dashboard':
                return 'locationMachinesTableHeader';
                break;
            case 'zone-dashboard':
                return 'zoneMachinesTableHeader';
                break;
            case 'acs-machines':
                return 'companyMachinesTableHeader';
                break;
            case 'location-acs-dashboard':
                return 'locationMachinesTableHeader';
                break;
            case 'zone-acs-dashboard':
                return 'zoneMachinesTableHeader';
                break;
        }
    }

    // Get saved machines table customization option name
    public function getSavedMachinesTableKeyName($name)
    {
        switch($name) {
            case 'dashboard-analytics':
                return 'companySavedMachinesTableHeader';
                break;
            case 'location-dashboard':
                return 'locationSavedMachinesTableHeader';
                break;
            case 'acs-machines':
                return 'companySavedMachinesTableHeader';
                break;
            case 'location-acs-dashboard':
                return 'locationSavedMachinesTableHeader';
                break;
        }
    }

    public function averagedSeries($collection, $series_count = 200, $devide_by = 1)
    {
        $total = $collection->count();
        $chunks = $collection->chunk($total / $series_count + 1);

        $ret = $chunks->map(function ($chunk) use ($devide_by) {
            $timestamp = ($chunk->first()->timestamp + $this->timeshift) * 1000;
            $values = $chunk->map(function ($value) use ($devide_by) {
                return json_decode($value->values)[0] / $devide_by;
            });
            return [$timestamp, round(array_sum($values->all()) / $chunk->count(), 2)];
        });

        return $ret;
    }

    public function testFunction(Request $request)
    {
        set_time_limit(0);

        $limit = $request->limit;

        $devices = DeviceData::where('created_at', '')->limit($limit)->get();

        foreach ($devices as $device) {
            $device->update([
                'created_at' => gmdate("D, d M Y H:i:s \G\M\T", $device->timestamp)
            ]);
        }

        dd($devices);
    }

    public function testAzureJson(Request $request)
    {
        $client = new Client();
        try {
            $response = $client->post(
                config('app.acs_middleware_url'),
                [
                    'json' => $request->all()
                ]
            );

            return $response->getBody();
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            return response()->json(json_decode($e->getResponse()->getBody()->getContents(), true), $e->getCode());
        }
    }
}

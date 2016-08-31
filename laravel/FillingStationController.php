<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Models\FillingStations;
use App\Models\User;
use App\Models\FuelTypes;
use App\Models\MapObjects;
use App\Models\Languages;
use App\Http\Requests\FillingStationRequest;

use App\Libraries\Images as ImageLib;

/**
 * Class FillingStationController
 * @package App\Http\Controllers
 */
class FillingStationController extends Controller
{
    /**
     *
     */
    public function __construct()
    {
        \App::setLocale('am');
    }

    /**
     * Display a listing of the Filling stations.
     *
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $response = [
            'status' => true,
            'status_code' => 200
        ];

        try {
            $filling_stations = FillingStations::with("map_object")->get();

            /* Fuel types, user/owner object */
            if (!empty($filling_stations)) {
                for ($i = 0; $i < count($filling_stations); $i++) {
                    $filling_stations[$i]['fuel_types'] = FuelTypes::whereIn("id", json_decode($filling_stations[$i]['fuel_types_ids']))->get();

                    /*TODO - check user's permissions attaching user's object*/
                    $filling_stations[$i]['user'] = User::where("id", '=', $filling_stations[$i]['user_id'])->get();
                }
            }

            $response['result'] = $filling_stations;
        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 404;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }

    /**
     * Store a newly created filling station in storage.
     *
     * @param  \Illuminate\Http\FillingStationRequest $request
     *
     * @example request post fields
     *  user_id:
     *    integer(required)
     *    ex. 1
     *  fuel_types_ids:
     *    array(required)
     *    ex. [1, 2, 3]
     *  phone_number:
     *    numeric
     *    ex. 095451287
     *  map_object_id:
     *    integer (optional required)
     *    ex. 5
     *  | or |
     *  map_object:
     *    json (optional required)
     *    ex. {"lat":"50.23", "lng":"40.00", "am": {"title": "title1", "address": "addr1"}}
     *  am:
     *    json (required)
     *    ex. {"cps_name":"name1"}
     *  ru:
     *    json
     *  en:
     *    json
     *  image:
     *    file image
     *
     * @return \Illuminate\Http\Response
     */
    public function create(FillingStationRequest $request)
    {
        $response = [
            'status' => true,
            'status_code' => 201
        ];

        try {
            $filling_station = new FillingStations();

            /* check user id */
            if (!User::where('id', '=', $request->input('user_id'))->exists())
                throw new Exception("User doesn't exist -> id: " . $request->input('user_id'));
            $filling_station->user_id = $request->input('user_id');


            /*check fuel types*/
            $fuel_types_ids = json_decode($request->input('fuel_types_ids'));
            if (empty($fuel_types_ids))
                throw new Exception("Fuel ids error");

            foreach ($fuel_types_ids as $fuel_type_id) {
                if (!FuelTypes::where('id', '=', $fuel_type_id)->exists())
                    throw new Exception("Fuel type doesn't exist -> id: " . $fuel_type_id);
            }
            $filling_station->fuel_types_ids = $request->input('fuel_types_ids');

            if (!empty($request->input('phone_number')))
                $filling_station->phone_number = $request->input('phone_number');

            /*
             * check map object
             * if there is a map_object_id -> save it in filling_station object
             * otherwise create/update map_object then get and save its id into filling_station object
             */
            if (!empty($request->input('map_object_id'))) {
                if (!MapObjects::where('id', '=', $request->input('map_object_id'))->exists())
                    throw new Exception("Map object doesn't exist -> id: " . $request->input('map_object_id'));

                $filling_station->map_object_id = $request->input('map_object_id');
            } elseif (!empty($request->input('map_object'))) {
                $map_object = json_decode($request->input('map_object'));
                $map_object->id = MapObjects::setMapObject($map_object);
                $filling_station->map_object_id = $map_object->id;
            } else {
                throw new Exception("Map object or Map object id doesn't exist");
            }

            /*translations*/
            $languages = Languages::all();
            foreach ($languages as $lang) {
                if (!empty($request->input($lang->locale))) {
                    $translation = json_decode($request->input($lang->locale));
                    $filling_station->translateOrNew($lang->locale)->cps_name = $translation->cps_name;
                }
            }

            /*TODO - check user's permissions*/
            $filling_station->status = 1;

            if (!$filling_station->save())
                throw new Exception("Error saving Filling station");

            /*Image upload*/
            $image_lib = new ImageLib();
            $file = $request->file('image');
            if(!empty($file)) {
                $image = $image_lib->upload($file, \Config::get('filesystems.disks.local.common'));
                $image_lib->bind($image, $filling_station);
            }

            $response['result'] = $filling_station;

        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 400;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }

    /**
     * Display the specified filling station.
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $response = [
            'status' => true,
            'status_code' => 200
        ];

        try {
            $filling_station = FillingStations::with("map_object")->where('id', '=', $id)->first();

            if ($filling_station) {
                /* Fuel types, owner object */
                $filling_station['fuel_types'] = FuelTypes::whereIn("id", json_decode($filling_station['fuel_types_ids']))->get();

                /*TODO - check user's permissions attaching user's object*/
                $filling_station['user'] = User::where("id", '=', $filling_station['user_id'])->get();
            }else{
                throw new Exception("Not found");
            }

            $response['result'] = $filling_station;
        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 404;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }

    /**
     * Update the specified filling station in storage.
     *
     * @param  \Illuminate\Http\FillingStationRequest $request
     * @param  int $id
     *
     * @example request post fields
     *  user_id:
     *    integer(required)
     *    ex. 1
     *  fuel_types_ids:
     *    array(required)
     *    ex. [1, 2, 3]
     *  phone_number:
     *    numeric
     *    ex. 095451287
     *  map_object_id:
     *    integer (optional required)
     *    ex. 5
     *  | or |
     *  map_object:
     *    json (optional required)
     *    ex. {"lat":"50.23", "lng":"40.00", "am": {"title": "title1", "address": "addr1"}}
     *  am:
     *    json (required)
     *    ex. {"cps_name":"name1"}
     *  ru:
     *    json
     *  en:
     *    json
     *  image:
     *    file image
     *
     * @return \Illuminate\Http\Response
     */
    public function update(FillingStationRequest $request, $id)
    {
        $response = [
            'status' => true,
            'status_code' => 202
        ];

        try {
            $filling_station = FillingStations::where('id', '=', $id)->first();
            if (!$filling_station)
                throw new Exception("Record doesn't exist.");

            /* check user id */
            if (!User::where('id', '=', $request->input('user_id'))->exists())
                throw new Exception("User doesn't exist -> id: " . $request->input('user_id'));
            $filling_station->user_id = $request->input('user_id');


            /*check fuel types*/
            $fuel_types_ids = json_decode($request->input('fuel_types_ids'));
            if (empty($fuel_types_ids))
                throw new Exception("Fuel ids error");

            foreach ($fuel_types_ids as $fuel_type_id) {
                if (!FuelTypes::where('id', '=', $fuel_type_id)->exists())
                    throw new Exception("Fuel type doesn't exist -> id: " . $fuel_type_id);
            }
            $filling_station->fuel_types_ids = $request->input('fuel_types_ids');

            if (!empty($request->input('phone_number')))
                $filling_station->phone_number = $request->input('phone_number');

            /*
             * check map object
             * if there is a map_object_id -> save it in filling_station object
             * otherwise create/update map_object then get and save its id into filling_station object
             */
            if (!empty($request->input('map_object_id'))) {
                if (!MapObjects::where('id', '=', $request->input('map_object_id'))->exists())
                    throw new Exception("Map object doesn't exist -> id: " . $request->input('map_object_id'));

                $filling_station->map_object_id = $request->input('map_object_id');
            } elseif (!empty($request->input('map_object'))) {
                $map_object = json_decode($request->input('map_object'));
                $map_object->id = MapObjects::setMapObject($map_object);
                $filling_station->map_object_id = $map_object->id;
            } else {
                throw new Exception("Map object or Map object id doesn't exist");
            }

            /*translations*/
            $languages = Languages::all();
            foreach ($languages as $lang) {
                if (!empty($request->input($lang->locale))) {
                    $translation = json_decode($request->input($lang->locale));
                    $filling_station->translateOrNew($lang->locale)->cps_name = $translation->cps_name;
                }
            }

            /*TODO - check user's permissions*/
            $filling_station->status = 1;

            if (!$filling_station->save())
                throw new Exception("Error saving Filling station");

            /*Image upload*/
            $image_lib = new ImageLib();
            $file = $request->file('image');
            if(!empty($file)) {
                $image = $image_lib->upload($file, \Config::get('filesystems.disks.local.common'));
                $image_lib->bind($image, $filling_station);
            }

            $response['result'] = $filling_station;

        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 400;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }

    /**
     * Remove the specified filling station from storage.
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        $response = [
            'status' => true,
            'status_code' => 203
        ];

        try {
            FillingStations::where('id', '=', $id)->delete();
            $response['result'] = $id;
        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 400;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

use App\Http\Requests;
use App\Http\Requests\UserRequest;
use App\Http\Controllers\Controller;

use App\Models\User;
use Bican\Roles\Models\Role;
use Bican\Roles\Models\Permission;

class UserController extends Controller
{
    /**
     * Display a listing of the Users.
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
            $users = User::all();

            /* Users */
//            if (!empty($users)) {
//                for ($i = 0; $i < count($users); $i++) {
//
//                }
//            }

            $response['result'] = $users;
        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 404;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }

    /**
     * Store a newly created user in storage.
     *
     * @example request post fields
     *  email:
     *    string(required)
     *    ex. example@host.com
     *  first_name:
     *    string(required)
     *    ex. name_n
     *  last_name:
     *    string
     *    ex. last_name
     *  password:
     *    string
     *    ex. pwd123
     *  language:
     *    string
     *    ex. am
     *  image:
     *    file
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function create(UserRequest $request)
    {
        $response = [
            'status' => true,
            'status_code' => 201
        ];

        try {
            $user = new User();

            if (!empty($request->input('email'))) {
                $user->email = $request->input('email');
            }

            if (!empty($request->input('first_name'))) {
                $user->first_name = $request->input('first_name');
            }

            if (!empty($request->input('last_name'))) {
                $user->last_name = $request->input('last_name');
            }

            if (!empty($request->input('password'))) {
                $user->password = bcrypt($request->input('password'));
            }

            if (!empty($request->input('language'))) {
                $user->language = $request->input('language');
            }else{
                $user->language = "am";
            }

            /*TODO - check user's permissions*/
            $user->status = 1;

            /*AVATAR upload*/
            $file = $request->file('image');
            if(!empty($file)){
                $image_folder = \Config::get('filesystems.disks.local.users');
                $file_name = md5($user->first_name."_".microtime());
                $extension = $file->getClientOriginalExtension();

                Storage::disk('local')->put($image_folder."/".$file_name.'.'.$extension,  File::get($file));
                $user->image = $file_name.'.'.$extension;
            }

            if (!$user->save())
                throw new Exception("Error saving User account");

            $response['result'] = $user;

        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 400;
            $response['error'] = $e->getMessage();
        }


        return \Response::json($response, $response['status_code']);
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $response = [
            'status' => true,
            'status_code' => 200
        ];

        try {
            $user = User::where('id', '=', $id)->first();

            $response['result'] = $user;
        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 404;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     *
     * @example request post fields
     *  email:
     *    string(required)
     *    ex. example@host.com
     *  first_name:
     *    string(required)
     *    ex. name_n
     *  last_name:
     *    string
     *    ex. last_name
     *  password:
     *    string
     *    ex. pwd123
     *  language:
     *    string
     *    ex. am
     *  image:
     *    file
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UserRequest $request, $id)
    {
        $response = [
            'status' => true,
            'status_code' => 202
        ];

        try {
            $user = User::where("id", $id)->first();
            if (!$user)
                throw new Exception("Record doesn't exist.");

            if (!empty($request->input('email'))) {
                $user->email = $request->input('email');
            }

            if (!empty($request->input('first_name'))) {
                $user->first_name = $request->input('first_name');
            }

            if (!empty($request->input('last_name'))) {
                $user->last_name = $request->input('last_name');
            }

            if (!empty($request->input('password'))) {
                $user->password = bcrypt($request->input('password'));
            }

            if (!empty($request->input('language'))) {
                $user->language = $request->input('language');
            }else{
                $user->language = "am";
            }

            /*AVATAR upload*/
            $file = $request->file('image');
            if(!empty($file)) {
                $image_folder = \Config::get('filesystems.disks.local.users');
                $file_name = md5($user->first_name."_".microtime());
                $extension = $file->getClientOriginalExtension();

                /*delete old file*/
                if(!empty($user->image) && Storage::exists($image_folder."/".$user->image)) {
                    Storage::delete($image_folder."/".$user->image);
                }

                Storage::disk('local')->put($image_folder."/".$file_name.'.'.$extension,  File::get($file));
                $user->image = $file_name.'.'.$extension;
            }

            /*TODO - check user's permissions*/
            $user->status = 1;

            if (!$user->save())
                throw new Exception("Error saving User account");

            $response['result'] = $user;

        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 400;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($id)
    {
        $response = [
            'status' => true,
            'status_code' => 203
        ];

        try {
            //get user object
            $user = User::where('id', '=', $id)->first();
            if (!$user)
                throw new Exception("Record doesn't exist.");

            /*delete old file*/
            $image_folder = \Config::get('filesystems.disks.local.users');
            if(!empty($user->image) && Storage::exists($image_folder."/".$user->image)) {
                Storage::delete($image_folder."/".$user->image);
            }

            $user->delete();

            $response['result'] = $id;
        } catch (Exception $e) {
            $response['status'] = false;
            $response['status_code'] = 400;
            $response['error'] = $e->getMessage();
        }

        return \Response::json($response, $response['status_code']);
    }
}
<?php

namespace DTApi\Repository;

use Validator;
use Illuminate\Database\Eloquent\Model;
use DTApi\Exceptions\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Request;

class BaseRepository
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    /**
     * @var Model
     */
    protected $model;

    /**
     * @var array
     */
    protected $validationRules = [];

    /**
     * @param Model $model
     */

    

    public
        $__params = [],
        $__is_ajax = false,
        $__is_api_call = false,
        $__is_redirect = false,
        $__is_error = false,
        $__is_paginate = true, // to control pagination object
        $__is_collection = true, // for item detail response
        $__collection = true, // to control general response
        $__is_custom_collection = false, // to control custom collection resource
        $__module = '',
        $__view = '',
        $__flash = [];

    public
        $call_mode = 'admin';    // api, admin, web

    function __construct()
    {
        //echo \Route::getCurrentRoute()->getActionName();
        $this->model = $model;
        $this->_callSetup();
    }

    private function _callSetup()
    {
        $this->__call_mode = 'web';
        if (preg_match('#/api/#', \Request::url())) {

            $this->__is_api_call = true;
            $this->call_mode = 'api';
        }

        if (preg_match('/admin/', \Request::url())) {
            $this->call_mode = 'admin';
        }

        if ($this->__is_api_call) {
            $this->middleware(ApiAuth::class);
        }
    }

    /**
     * @return array
     */
    public function validatorAttributeNames()
    {
        return [];
    }

    /**
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|Model[]
     */
    public function all()
    {
        return $this->model->all();
    }

    /**
     * @param integer $id
     * @return Model|null
     */
    public function find($id)
    {
        return $this->model->find($id);
    }

    public function with($array)
    {
        return $this->model->with($array);
    }

    /**
     * @param integer $id
     * @return Model
     * @throws ModelNotFoundException
     */
    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    /**
     * @param string $slug
     * @return Model
     * @throws ModelNotFoundException
     */
    public function findBySlug($slug)
    {

        return $this->model->where('slug', $slug)->first();

    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return $this->model->query();
    }

    /**
     * @param array $attributes
     * @return Model
     */
    public function instance(array $attributes = [])
    {
        $model = $this->model;
        return new $model($attributes);
    }

    /**
     * @param int|null $perPage
     * @return mixed
     */
    public function paginate($perPage = null)
    {
        return $this->model->paginate($perPage);
    }

    public function where($key, $where)
    {
        return $this->model->where($key, $where);
    }

    /**
     * @param array $data
     * @param null $rules
     * @param array $messages
     * @param array $customAttributes
     * @return \Illuminate\Validation\Validator
     */
    public function validator(array $data = [], $rules = null, array $messages = [], array $customAttributes = [])
    {
        if (is_null($rules)) {
            $rules = $this->validationRules;
        }

        return Validator::make($data, $rules, $messages, $customAttributes);
    }

    /**
     * @param array $data
     * @param null $rules
     * @param array $messages
     * @param array $customAttributes
     * @return bool
     * @throws ValidationException
     */

    protected function __validateRequestParams($input_params, $param_rules, $param_rules_messages = [])
    {
        $this->__params = $input_params;
        $this->__customMessages = [];
        if (!empty($param_rules_messages))
            $this->__customMessages = $param_rules_messages;

        $validator = \Validator::make($input_params, $param_rules, $this->__customMessages);

        $errors = [];

        if ($validator->fails()) {
            foreach ($param_rules as $field => $value) {
                $message = $validator->errors()->first($field);
                if (!empty($message)) {
                    $errors[$field] = $message;
                    if (isset($this->__customMessages[$field])) {
                        $errors[$field] = $this->__customMessages[$field];
                    }
                }
            }
            $this->__is_error = true;

            if ($this->__is_api_call)
                return $this->__sendError('Validation Error.', $errors);

            if ($this->__is_ajax)
                return $this->__sendError('Validation Error.', $errors);

            if ($this->__is_redirect) {
                return $this->__sendError('Validation Error.', $errors);

                return redirect(\URL::to($this->__module . $this->__view));
            }

            return View::make($this->__module . $this->__view, ['error' => $this->__sendError('Validation Error.', $errors), 'page' => $this->__view]);
        }
        return $response = [
            'code' => 200,
            'success' => true,
            'message' => 'success',
        ];
    }

     protected function __sendResponse($resource, $obj_model, $response_code, $message)
    {
        $page_info = $this->__getPaginate($obj_model);

        $resource = "\App\Http\Resources\\$resource";
        if ($this->__is_custom_collection) {
            $this->__collection = false;
            $this->__is_collection = false;

            $custom_resource_obj = new $resource();
            $collection = [];
            foreach ($obj_model as $row) {
                $collection[] = $custom_resource_obj->toArray($row);
            }
            $obj_model = [];
            $obj_model = $collection;
            if (count($obj_model) < 1) {
                $message = 'No data found.';
                $response_code = 204;
            }
        }
        if ($this->__collection && $this->__is_collection) {
            $result = $resource::collection($obj_model);
            // when data record set is empty
            if ($this->__collection && $result->isEmpty()) {
                $message = 'No data found.';
                $response_code = 204;
            }
        }

        if ($this->__collection && !$this->__is_collection) {
            $result = new $resource($obj_model);
            // when data record set is empty
            if ($this->__collection && !$result->exists) {
                $message = 'No data found.';
                $response_code = 204;
            }
        }

        if (!$this->__collection) {
            $result = $obj_model;
        }

        $response = [
            'code' => $response_code,
            //'success' => true,
            'data' => ($this->__collection) ? $result : $obj_model,
            'message' => $message,
            'links' => $page_info['links'],
            'meta' => $page_info['meta'],
            'draw' => !empty(\Request::input('draw')) ? (\Request::input('draw') + 1) : 0,
            'recordsTotal' => $page_info['meta']['total'],
            'recordsFiltered' => $page_info['meta']['total'],
        ];

        if ($this->__is_api_call)
            return response()->json($response, 200);

        if ($this->__is_ajax)
            return response()->json($response, 200);

        if ($this->__is_redirect)
            return redirect(\URL::to($this->__module . $this->__view));

        $data = isset($result->collection) ? json_decode($result->collection) : $result;
        return View::make($this->__module . $this->__view, ['data' => $data, 'page' => $this->__view]);

        //print 'html response';
        //exit;
    }

    public function __sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'code' => $code,
            //    'success' => false,
            'message' => $error,
        ];


        if (!empty($errorMessages))
            $response['data'][] = $errorMessages;

        if ($this->__is_api_call)
            return response()->json($response, $code);

        if ($this->__is_ajax)
            return response()->json($response, 200);

        if ($this->__is_redirect) {
            $request = Request();
            $request->session()->put([
                'error' => $response,
            ]);
            return redirect(\URL::to($this->__module . $this->__view));
        }

        return View::make($this->__module . $this->__view, ['error' => $response, 'page' => $this->__view]);
        //print_r($response);exit;
        return $response;
    }
    
    public function validate(array $data = [], $rules = null, array $messages = [], array $customAttributes = [])
    {
        $validator = $this->validator($data, $rules, $messages, $customAttributes);
        return $this->_validate($validator);
    }

    /**
     * @param array $data
     * @return Model
     */
    public function create(array $data = [])
    {
        return $this->model->create($data);
    }

    /**
     * @param integer $id
     * @param array $data
     * @return Model
     */
    public function update($id, array $data = [])
    {
        $instance = $this->findOrFail($id);
        $instance->update($data);
        return $instance;
    }

    /**
     * @param integer $id
     * @return Model
     * @throws \Exception
     */
    public function delete($id)
    {
        $model = $this->findOrFail($id);
        $model->delete();
        return $model;
    }

    /**
     * @param \Illuminate\Validation\Validator $validator
     * @return bool
     * @throws ValidationException
     */
    protected function _validate(\Illuminate\Validation\Validator $validator)
    {
        if (!empty($attributeNames = $this->validatorAttributeNames())) {
            $validator->setAttributeNames($attributeNames);
        }

        if ($validator->fails()) {
            return false;
            throw (new ValidationException)->setValidator($validator);
        }

        return true;
    }

}
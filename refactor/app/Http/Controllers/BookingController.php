<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use App\Http\Middleware\LoginAuth;
/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->middleware(LoginAuth::class, ['only' => ['storeBooking']]);
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobs($user_id);

        }
        elseif($request->__authenticatedUser->user_type == env('ADMIN_ROLE_ID') || $request->__authenticatedUser->user_type == env('SUPERADMIN_ROLE_ID'))
        {
            $response = $this->repository->getAll($request);
        }

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        return response($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    
    public function storeBooking(Request $request)
    {
        $params = $request->all();
        $param_rules['immediatetime'] = 5;
        $param_rules['from_language_id'] = 'required|array|exists:language,id,deleted_at,NULL';
        $param_rules['immediate'] = 'required|string|in:yes,no';
        $param_rules['due_date'] = 'nullable|date_format:"Y-m-d"';
        $param_rules['due_time'] = 'nullable|date_format:"Y-m-d"';
        $param_rules['customer_phone_type'] = 'required|string|in:yes,no';
        $param_rules['customer_physical_type'] = 'required|string|in:yes,no';
        $param_rules['duration'] = 'required|string';
        $param_rules['customer_phone_type'] = 'required|in:yes,no';
        $param_rules['customer_physical_type'] = 'required|in:yes,no';
        $param_rules['job_for']      = 'array|in:male,female,normal,certified,certified_in_law,certified_in_helth';

        $this->__is_ajax = true;

        $response = $this->__validateRequestParams($request->all(), $param_rules,[]);

        if ($this->__is_error == true)
            return $response;

        if($params['user_type'] != env('CUSTOMER_ROLE_ID')){
            $errors['booking'] = 'Translator can not create booking';
            return $this->__sendError('Validation Error.', $errors);
        }

        if($params['immediate'] == 'no'){
            if($params['due_date'] == ''){
                $errors['due_date'] = 'Du måste fylla in alla fält';
            }elseif($params['due_time'] == ''){
                $errors['due_time'] = 'Du måste fylla in alla fält';
            }
            return $this->__sendError('Validation Error.', $errors);
        }elseif ($params['immediate'] == 'yes') {
            $due_carbon = Carbon::now()->addMinute($immediatetime);
            $params['due'] = $due_carbon->format('Y-m-d H:i:s');
            $params['immediate'] = 'yes';
            $params['customer_phone_type'] = 'yes';
            $params['type'] = 'immediate';
        }else{
            $due = $data['due_date'] . " " . $data['due_time'];
            $params['type'] = 'regular';
            $due_carbon = Carbon::createFromFormat('m/d/Y H:i', $due);
            $params['due'] = $due_carbon->format('Y-m-d H:i:s');
            if ($due_carbon->isPast()) {
                $errors['message'] = "Can't create booking in past";
                return $this->__sendError('Validation Error.', $errors);
            }
        }

        if($params['job_for'][0] == 'male' || $params['job_for'][0] == 'female'){
            $params['gender'] = $params['job_for'];
        }elseif ($params['job_for'][0] == 'normal') {
                $params['certified'] = $params['job_for'];
        }elseif ($params['job_for'][0] == 'certified') {
                $params['certified'] = 'yes';
        }elseif ($params['job_for'][0] == 'certified_in_law') {
                $params['certified'] = 'law';
        }elseif ($params['job_for'][0] == 'certified_in_helth') {
                $params['certified'] = 'health';
        }elseif(in_array('normal', $params['job_for']) && in_array('certified', $params['job_for'])){
                $params['certified'] = 'both';
        }elseif(in_array('normal', $params['job_for']) && in_array('certified_in_law', $params['job_for'])){
                $params['certified'] = 'n_law';
        }elseif(in_array('normal', $params['job_for']) && in_array('certified_in_helth', $params['job_for'])){
                $params['certified'] = 'n_health';
        }

        if ($consumer_type == 'rwsconsumer')
            $params['job_type'] = 'rws';
        else if ($consumer_type == 'ngo')
            $params['job_type'] = 'unpaid';
        else if ($consumer_type == 'paid')
            $params['job_type'] = 'paid';
        $params['b_created_at'] = date('Y-m-d H:i:s');

        if (isset($due))
            $params['will_expire_at'] = TeHelper::willExpireAt($due, $params['b_created_at']);
            $params['by_admin'] = isset($params['by_admin']) ? $params['by_admin'] : 'no';

        $response = $this->repository->create($params);

        return $this->__sendResponse('Booking', $response, 200, 'Booking has been added successfully.');
    }

    public function store(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->store($params);

        return response($response);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        if (isset($data['distance']) && $data['distance'] != "") {
            $distance = $data['distance'];
        } else {
            $distance = "";
        }
        if (isset($data['time']) && $data['time'] != "") {
            $time = $data['time'];
        } else {
            $time = "";
        }
        if (isset($data['jobid']) && $data['jobid'] != "") {
            $jobid = $data['jobid'];
        }

        if (isset($data['session_time']) && $data['session_time'] != "") {
            $session = $data['session_time'];
        } else {
            $session = "";
        }

        if ($data['flagged'] == 'true') {
            if($data['admincomment'] == '') return "Please, add comment";
            $flagged = 'yes';
        } else {
            $flagged = 'no';
        }
        
        if ($data['manually_handled'] == 'true') {
            $manually_handled = 'yes';
        } else {
            $manually_handled = 'no';
        }

        if ($data['by_admin'] == 'true') {
            $by_admin = 'yes';
        } else {
            $by_admin = 'no';
        }

        if (isset($data['admincomment']) && $data['admincomment'] != "") {
            $admincomment = $data['admincomment'];
        } else {
            $admincomment = "";
        }
        if ($time || $distance) {

            $affectedRows = Distance::where('job_id', '=', $jobid)->update(array('distance' => $distance, 'time' => $time));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {

            $affectedRows1 = Job::where('id', '=', $jobid)->update(array('admin_comments' => $admincomment, 'flagged' => $flagged, 'session_time' => $session, 'manually_handled' => $manually_handled, 'by_admin' => $by_admin));

        }

        return response('Record updated!');
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}

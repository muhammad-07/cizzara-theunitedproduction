<?php

namespace App\Http\Controllers;

use App\Exports\ContestantsExport;
use App\Exports\UsersExport;
use App\Models\Audition;
use App\Models\Plan;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoRating;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class AdminVideoController extends Controller
{
    public function index(Request $request)
    {
        $query = Video::with('user');

        if (empty($request->submit)) {
            $query->where('status', '!=', 'rejected');
        } else {
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('contestant') && !empty($request->contestant)) {
                $query->whereHas('user', function ($userQuery) use ($request) {
                    $userQuery->where('name', 'like', '%' . $request->contestant . '%');
                });
            }
        }
        $query->orderByRatings('asc');
        $videos = $query->paginate(env('RECORDS_PER_PAGE', 10));

        return view('admin.videos', compact('videos'));
    }
    public function show(Video $video)
    {
        $video->auditionDetails = $video->auditionDetails();

        return view('admin.show', compact('video'));
    }

    public function user($user_id)
    {
        $user = User::where('id', $user_id)->with('details')->first();
        return view('admin.users.show', compact('user'));
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|in:' . implode(',', config('app.audition_status')) . ',rejected',
        ]);

        $updated = Audition::where('plan_id', $request->audition)->where('user_id', $request->user)->update(['status' => $request->status]);
        return response()->json(['success' => $updated, $request->all()]);
    }



    public function userList(Request $request)
    {
        $query = User::where('id', '!=', auth()->user()->id);

        if ($request->has('contestant') && !empty($request->contestant)) {
            $query->whereHas('details', function ($userQuery) use ($request) {
                $userQuery->where('first_name', 'like', '%' . $request->contestant . '%')
                    ->orWhere('last_name', 'like', '%' . $request->contestant . '%');
            });
        } else {
            $query->whereHas('details');
        }
        $users = $query->paginate(env('RECORDS_PER_PAGE', 10));
        return view('admin.users.index', compact('users'));
    }
    public function exportUserList(Request $request)
    {
        $selectedRecordIds = $request->input('selectedRecords');

        $query = User::where('id', '!=', auth()->user()->id)->whereHas('details');

        if ($selectedRecordIds)
            $selectedRecords = $query->whereIn('id', $selectedRecordIds)->get();
        else
            $selectedRecords = $query->get();

        return Excel::download(new UsersExport($selectedRecords), 'users-list.xlsx');
    }



    public function audition($user_id)
    {
        // $plan = Plan::where('name', 'SingTUV2024');
        // $auditions = Video::where('plan_id', $plan->id)->orderBy('user_id')->get();
        // dd($auditions);
        $user = User::where('id', $user_id)->with('details')->first();
        return view('admin.users.show', compact('user'));
    }


    public function topListxxx(Request $request, $top = null, $audition = null)
    {
        if ($request->audition != "") {
            $plan = Plan::where('name', $request->audition)->first();
        } else {
            $plan = Plan::latest()->first();
        }

        if (empty($plan))
            return redirect()->route('admin.videos.index')->with('error', 'Select an audition first. #65d');

        // Retrieve the top 3 users with their average ratings
        $topUsers = User::select('users.*', DB::raw('IFNULL(AVG(video_ratings.rating), 0) as average_rating'))
            ->whereHas('details')
            ->join('videos', 'users.id', '=', 'videos.user_id')
            ->leftJoin('video_ratings', 'videos.id', '=', 'video_ratings.video_id')
            ->where('videos.plan_id', $plan->id)
            ->groupBy('users.id')
            ->orderByDesc('average_rating')
            ->take($request->top ?? 3)
            ->get();

        // Manually create a LengthAwarePaginator instance for the top users
        $perPage = env('RECORDS_PER_PAGE', 10); // Set per page to 2 for the first page
        $currentPage = $request->query('page', 1);

        // Calculate the offset based on the current page
        $offset = ($currentPage - 1) * $perPage;

        // Get the items for the current page
        $items = collect(array_slice($topUsers->toArray(), $offset, $perPage));

        // Create a LengthAwarePaginator instance
        $paginatedTopUsers = new LengthAwarePaginator(
            $items,
            count($topUsers), // Total count of items
            $perPage, // Per page
            $currentPage // Current page
        );
        $topUsers = $items;

        $paginatedTopUsers->setPath($request->url());
        $audition = $plan->id;
        return view('admin.auditions.top', compact('paginatedTopUsers', 'topUsers', 'audition'));
    }



    public function topList(Request $request)
    {
        if ($request->audition != "") {
            $plan = Plan::where('name', $request->audition)->first();
        } else {
            $plan = Plan::latest()->first();
        }

        if (empty($plan))
            return redirect()->route('admin.auditions.top')->with('error', 'Select an audition first. #65d');

        $topUsers = Audition::where('plan_id', $plan->id)
            ->with('user.details')
            ->with('user.videos');

        if ($request->status && !empty($request->status)) {
            $topUsers->where('status', $request->status);
        }
        $topUsers = $topUsers->get();

        $paginatedTopUsers = [];

        $plans = Plan::all();
        return view('admin.auditions.top', compact('paginatedTopUsers', 'topUsers', 'plans'));


        // Retrieve the top 3 users with their average ratings
        // $topUsers = User::select('users.*', DB::raw('IFNULL(AVG(video_ratings.rating), 0) as average_rating'))
        // ->whereHas('details')
        //     ->join('videos', 'users.id', '=', 'videos.user_id')
        //     ->leftJoin('video_ratings', 'videos.id', '=', 'video_ratings.video_id')
        //     ->where('videos.plan_id', $plan->id)
        //     ->groupBy('users.id')
        //     ->orderByDesc('average_rating')
        //     ->take($request->top ?? 3)
        //     ->get();

        // // Manually create a LengthAwarePaginator instance for the top users
        // $perPage = env('RECORDS_PER_PAGE', 10); // Set per page to 2 for the first page
        // $currentPage = $request->query('page', 1);

        // // Calculate the offset based on the current page
        // $offset = ($currentPage - 1) * $perPage;

        // // Get the items for the current page
        // $items = collect(array_slice($topUsers->toArray(), $offset, $perPage));

        // // Create a LengthAwarePaginator instance
        // $paginatedTopUsers = new LengthAwarePaginator(
        //     $items,
        //     count($topUsers), // Total count of items
        //     $perPage, // Per page
        //     $currentPage // Current page
        // );
        // $topUsers = $items;

        // $paginatedTopUsers->setPath($request->url());
        // return view('admin.auditions.top', compact('paginatedTopUsers', 'topUsers'));
    }

    public function exportToppers(Request $request)
    {
        $selectedRecordIds = $request->input('selectedRecords');

        $topUsers = Audition::
        // where('plan_id', $plan->id)
            with('user.details')
            ->with('user.videos');

        if ($selectedRecordIds)
            $selectedRecords = $topUsers->whereIn('auditions.id', $selectedRecordIds)->get();
        else {
            if ($request->audition != "") {
                $plan = Plan::where('name', $request->audition)->first();
            } else {
                $plan = Plan::latest()->first();
            }
            $selectedRecords = $topUsers->where('plan_id', $plan->id)->get();

        }

        return Excel::download(new ContestantsExport($selectedRecords), 'toppers.xlsx');
    }


    // public function auditionList(Request $request)
    // {
    //     $plan = Plan::where('name', 'SingTUV2024')->first();

    //     $query = User::withVideosByAudition($plan->id); //->get();

    //     $users = $query->paginate(env('RECORDS_PER_PAGE', 10));
    //     return view('admin.auditions.index', compact('users'));
    // }

    public function auditionList(Request $request)
    {
        $plan = Plan::where('name', 'SingTUV2024')->first();

        $query = Audition::with('user')
            ->where('auditions.plan_id', $plan->id);

        $auditions = $query->paginate(env('RECORDS_PER_PAGE', 10));
        return view('admin.auditions.index', compact('auditions'));
    }


    // public function exportToppers(Request $request)
    // {
    //     $selectedRecordIds = $request->input('selectedRecords');

    //     $plan = Plan::where('name', 'SingTUV2024')->first();

    //     $qry = User::select(
    //         'users.id',
    //         'users.email',

    //         'user_details.first_name',
    //         'user_details.last_name',
    //         // 'stagename' => 'nullable|string|max:255',
    //         'user_details.gender',
    //         'user_details.relationship_status',
    //         'user_details.date_of_birth',
    //         'user_details.address',
    //         'user_details.city',
    //         'user_details.state',
    //         'user_details.pin_code',
    //         'user_details.phone',
    //         'user_details.education',
    //         'user_details.occupation',
    //         'user_details.work_experience',

    //         'user_details.hobbies',
    //         'user_details.describe_yourself',
    //         'user_details.instagram',
    //         'user_details.youtube',
    //         'user_details.facebook',

    //         'user_details.g_first_name',
    //         'user_details.g_last_name',
    //         'user_details.g_address',
    //         'user_details.g_city',
    //         'user_details.g_state',
    //         'user_details.g_pin_code',
    //         'user_details.g_phone',
    //         'user_details.g_email',

    //         DB::raw('IFNULL(AVG(video_ratings.rating), 0) as average_rating')
    //     )
    //         ->join('videos', 'users.id', '=', 'videos.user_id')
    //         ->leftJoin('video_ratings', 'videos.id', '=', 'video_ratings.video_id')
    //         ->join('user_details', 'users.id', '=', 'user_details.user_id')
    //         ->where('videos.plan_id', $plan->id)
    //         ->groupBy('users.id')
    //         ->orderByDesc('average_rating');


    //     if ($selectedRecordIds)
    //         $selectedRecords = $qry->whereIn('users.id', $selectedRecordIds)->get();
    //     else
    //         $selectedRecords = $qry->get();

    //     return Excel::download(new ContestantsExport($selectedRecords), 'toppers.xlsx');
    // }

    public function exportAudition(Request $request)
    {
        $selectedRecordIds = $request->input('selectedRecords');

        $plan = Plan::where('name', 'SingTUV2024')->first();

        $qry = User::withVideosByAudition($plan->id); //->get();

        if ($selectedRecordIds)
            $selectedRecords = $qry->whereIn('users.id', $selectedRecordIds)->get();
        else
            $selectedRecords = $qry->get();

        return Excel::download(new ContestantsExport($selectedRecords), 'audition-list.xlsx');
    }
}

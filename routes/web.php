<?php

use App\Http\Controllers\AdminVideoController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AuditionController;
use App\Http\Controllers\PaypalController;
use App\Http\Controllers\UserDetailController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoRatingController;
use App\Mail\MyTestEmail;
use App\Models\Payment;
use App\Models\Audition;
use App\Models\Plan;
use App\Models\UserDetail;
use GuzzleHttp\Client;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Srmklive\PayPal\Facades\PayPal;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // $type = 'Group';
    // $plan_amt = Plan::where('name', 'TNDS-S1')->first();//->whereJsonContains('prices', 'Group')->first();
    // dd($plan_amt['prices'][$type]['Price']);
    return view('welcome', ['plans' => Plan::all()]);

})->name('welcome');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect('/home');
})->middleware(['auth', 'signed'])->name('verification.verify');
Route::get('/notify', function (Request $request) {


try {
//    $vc = new VideoController();
//    $vc->notifyAdmin($request);
$recipient = "918866202134";
$apiKey = env('WHATSAPP_API_KEY');
        $url = 'https://api.whatsapp.com/send?phone=' . $recipient . '&text=' . urlencode("message");

        $client = new Client(['verify'=>false]);
       
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
        dd($response->getBody()->getContents());
    // Email queued successfully
    echo "notifyAdmin queued for sending.";
} catch (\Exception $e) {
    // An error occurred while sending the email
    echo "Error sending notifyAdmin: " . $e->getMessage();
}


})->name('notifyAdmin');

Auth::routes(['verify' => true]);
Route::get('/top/{plan?}', [VideoRatingController::class, 'countAvg']);

Route::get('/home', function () {
    return view('welcome', ['plans' => Plan::all()]);
})->name('home');
Route::group(['middleware' => ['auth', 'verified']], function () {
    // Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

    Route::post('/get-pre-signed-url', [VideoController::class, 'generatePreSignedUrl'])->name('get-pre-signed-url');

    Route::get('/payment/{plan?}', [PaymentController::class, 'charge'])->name('goToPayment');
    Route::post('payment/process-payment/{plan?}', [PaymentController::class, 'processPayment'])->name('processPayment');


    Route::group(['prefix' => 'paypal'], function () {
        Route::post('/order/create', [PaypalController::class, 'create'])->name('paypal.create');
        Route::post('/order/capture/', [PayPalController::class, 'capture'])->name('paypal.capture');
    });



    // Route::middleware('isPaid')->group(function () {
        Route::resource('user-details', UserDetailController::class);
        Route::resource('audition', AuditionController::class);

        Route::get('/upload-video/{plan?}', [VideoController::class, 'index'])->name('upload-video');//->middleware('isPaid');
        Route::post('/upload-video', [VideoController::class, 'upload'])->name('video.upload')->middleware('isPaid');
        Route::get('/thank-you', function () {
            return view('thanks');
        })->name('thank-you')->middleware('isPaid');
    // });
});


// ========== Admin ================
Route::middleware(['role:guru|admin'])->group(function () {



    Route::get('/admin/videos', [AdminVideoController::class, 'index'])->name('admin.videos.index');
    // Route::put('/admin/videos/{video}/status', [AdminVideoController::class, 'updateStatus'])->name('admin.videos.updateStatus');
    Route::get('/admin/videos/{video}', [AdminVideoController::class, 'show'])->name('admin.videos.show');
    Route::get('/admin/videos/{guru}/{video}', [AdminVideoController::class, 'showByGuru'])->name('admin.videos.show-by-guru');

    Route::post('/admin/{video}/rate_video', [VideoRatingController::class, 'rateVideo'])->name('guru.rate.video');

    // Route::get('/admin/auditions', [AdminVideoController::class, 'auditionList'])->name('admin.auditions.index');
    Route::get('/admin/auditions', [AdminVideoController::class, 'topList'])->name('admin.auditions.index');

    Route::middleware(['role:admin'])->group(function () {
        // Route::get('/admin/gurus', [GuruController::class, 'index'])->name('admin.gurus.index');
        // Route::get('/admin/gurus/show', [GuruController::class, 'show'])->name('admin.gurus.show');
        Route::resource('gurus', GuruController::class);


        Route::post('/admin/gurus/updateStatus', [GuruController::class, 'updateStatus'])->name('admin.gurus.update-status');
        Route::post('/admin/gurus/updateAudition', [GuruController::class, 'updateAudition'])->name('admin.gurus.assign-audition');
        Route::post('/admin/gurus/ratingReminder', [GuruController::class, 'ratingReminder'])->name('admin.gurus.send-rating-reminder');




        Route::get('/admin/users', [AdminVideoController::class, 'userList'])->name('admin.users.index');
        Route::get('/admin/users/{user}', [AdminVideoController::class, 'user'])->name('admin.users.show');

        // Route::get('/admin/auditions/top/{top?}', [AdminVideoController::class, 'topList'])->name('admin.auditions.top');
        // Route::get('/admin/auditions/top/{audition?}/{status?}', [AdminVideoController::class, 'topList'])->name('admin.auditions.top');
        Route::get('/admin/auditions/top/{audition?}/{status?}/{top?}/{sort?}', [AdminVideoController::class, 'topList'])->name('admin.auditions.top');
        Route::post('/admin/auditions/top/updateStatus', [AdminVideoController::class, 'updateStatus'])->name('admin.auditions.updateStatus');

        Route::get('/admin/auditions/{audition}', [AdminVideoController::class, 'audition'])->name('admin.auditions.show');


        Route::post('/export-toppers', [AdminVideoController::class, 'exportToppers'])->name('export.toppers');
        Route::post('/export-exportUserList', [AdminVideoController::class, 'exportUserList'])->name('export.userList');
        Route::post('/export-audition', [AdminVideoController::class, 'exportaudition'])->name('export.audition');
    });
});

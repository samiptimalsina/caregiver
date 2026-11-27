<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Rules\CustomRecaptcha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\ContactMessage\app\Models\ContactMessage;

class AppointmentController extends Controller
{
    public function create()
    {
        return view('frontend.pages.book-appointment');
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'appointment_date' => ['required', 'date', 'after_or_equal:today'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];

        if (Cache::get('setting')?->recaptcha_status === 'active') {
            $rules['g-recaptcha-response'] = ['required', new CustomRecaptcha];
        }

        $validated = $request->validate($rules);

        $appointmentMessage = sprintf(
            "Appointment request\nPreferred date: %s\nPreferred time: %s\n\n%s",
            $validated['appointment_date'],
            $validated['appointment_time'],
            $validated['message'] ?? ''
        );

        $contactMessage = new ContactMessage;
        $contactMessage->name = $validated['name'];
        $contactMessage->email = $validated['email'];
        $contactMessage->phone = $validated['phone'];
        $contactMessage->subject = 'Appointment request';
        $contactMessage->message = $appointmentMessage;
        $contactMessage->save();

        return redirect()
            ->route('appointment.create')
            ->with('appointment_submitted', __('Your appointment request has been sent. Our team will contact you to confirm.'));
    }
}

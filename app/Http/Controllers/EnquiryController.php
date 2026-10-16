<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Models\Property;
use App\Services\EnquiryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EnquiryController extends Controller
{
    /**
     * EnquiryService is injected into the METHOD here, not the constructor.
     * Both work the same way (the service container builds it). Method injection
     * is handy when only one action of a controller needs the dependency.
     *
     * Note what is NOT here: no logging, no "update last enquiry", no email.
     * The controller only saves; the listeners decide what else happens.
     */
    public function store(StoreEnquiryRequest $request, Property $property, EnquiryService $enquiries): RedirectResponse
    {
        /** @var array{message: string, phone?: string|null} $data */
        $data = $request->validated();

        $enquiries->create($request->user(), $property, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Enquiry sent. The agency will contact you soon.',
        ]);

        return back();
    }
}

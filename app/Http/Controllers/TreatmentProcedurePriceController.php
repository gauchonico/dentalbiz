<?php

namespace App\Http\Controllers;

use App\Models\TreatmentProcedurePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TreatmentProcedurePriceController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manageTreatmentPrices');

        $q = trim((string) $request->input('q', ''));
        $query = TreatmentProcedurePrice::query()->orderBy('name');

        if ($q !== '') {
            $query->where('name', 'like', "%$q%");
        }

        $prices = $query->paginate(12)->withQueryString();

        return Inertia::render('Admin/TreatmentProcedurePrices', [
            'filters' => ['q' => $q],
            'prices' => $prices,
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manageTreatmentPrices');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'active' => 'boolean',
        ]);

        TreatmentProcedurePrice::create([
            'name' => $data['name'],
            'cost' => $data['cost'],
            'active' => $data['active'] ?? true,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Procedure price created');
    }

    public function update(Request $request, TreatmentProcedurePrice $price)
    {
        Gate::authorize('manageTreatmentPrices');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'cost' => 'required|numeric|min:0',
            'active' => 'boolean',
        ]);

        $price->update($data);

        return back()->with('success', 'Procedure price updated');
    }

    public function toggle(TreatmentProcedurePrice $price)
    {
        Gate::authorize('manageTreatmentPrices');

        $price->update(['active' => !$price->active]);

        return back();
    }

    public function destroy(TreatmentProcedurePrice $price)
    {
        Gate::authorize('manageTreatmentPrices');

        $price->delete();

        return back()->with('success', 'Procedure price deleted');
    }
}

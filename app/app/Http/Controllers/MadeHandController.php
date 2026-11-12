<?php

namespace App\Http\Controllers;

use App\Models\MadeHand;
use Illuminate\Http\Request;

class MadeHandController extends Controller
{
    public function index()
    {
        $madeHands = MadeHand::ordered()->get();
        $madeHandsGrouped = [
            'normal' => $madeHands->where('is_special', false),
            'special' => $madeHands->where('is_special', true),
        ];

        \Log::debug('First hand probability:', ['probability' => $madeHands->first()->probability ?? 'null']);

        return view('made-hands.index', compact('madeHandsGrouped', 'madeHands'));
    }

    public function create()
    {
        return view('made-hands.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:made_hands',
            'description' => 'nullable|string',
            'value' => 'required|numeric|min:0|max:999.99',
            'order' => 'required|integer|min:0',
            'is_special' => 'boolean',
            'type' => 'required|in:pair,two_pair,set,straight,flush,full_house,quads,draw_project,high_card',
            'applies_to_river' => 'boolean',
        ]);

        MadeHand::create($validated);

        return redirect()->route('made-hands.index')
            ->with('success', __('made-hands.create_success'));
    }

    public function edit(MadeHand $madeHand)
    {
        return view('made-hands.edit', compact('madeHand'));
    }

    public function update(Request $request, MadeHand $madeHand)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:made_hands,name,' . $madeHand->id,
            'description' => 'nullable|string',
            'value' => 'required|numeric|min:0|max:999.99',
            'order' => 'required|integer|min:0',
            'combos' => 'nullable|integer|min:0',
            'visuals' => 'nullable|string',
            'is_special' => 'boolean',
            'type' => 'required|in:pair,two_pair,set,straight,flush,full_house,quads,draw_project,high_card',
            'applies_to_river' => 'boolean',
        ]);

        // Process visuals as JSON
        if (!empty($validated['visuals'])) {
            try {
                $validated['visuals'] = json_decode($validated['visuals'], true);
                if (!is_array($validated['visuals'])) {
                    $validated['visuals'] = null;
                }
            } catch (\Exception $e) {
                $validated['visuals'] = null;
            }
        }

        $madeHand->update($validated);

        return redirect()->route('made-hands.index')
            ->with('success', __('made-hands.update_success'));
    }

    public function destroy(MadeHand $madeHand)
    {
        $madeHand->delete();

        return redirect()->route('made-hands.index')
            ->with('success', __('made-hands.delete_success'));
    }
}

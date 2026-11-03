<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use App\Models\Pipeline;
use App\Models\Stage;
use App\Models\WhatsAppNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Admin editors for pipeline stages and WhatsApp message templates. */
class SettingsPipelineController extends Controller
{
    public function updateStage(Request $request, Stage $stage): RedirectResponse
    {
        $this->own($stage);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'stale_after_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $stage->update($data + ['stale_after_days' => $stage->kind === 'open' ? ($data['stale_after_days'] ?? null) : null]);

        return back()->with('success', 'Stage saved.');
    }

    public function addStage(Request $request, Pipeline $pipeline): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'stale_after_days' => ['nullable', 'integer', 'min:1', 'max:365']]);
        $lastOpen = $pipeline->stages()->where('kind', 'open')->max('position') ?? 0;

        // Won and Lost sit after the open stages, so shift them to make room.
        $pipeline->stages()->where('position', '>', $lastOpen)->increment('position');
        $pipeline->stages()->create($data + ['kind' => 'open', 'position' => $lastOpen + 1]);

        return back()->with('success', 'Stage added.');
    }

    public function moveStage(Request $request, Stage $stage): RedirectResponse
    {
        $dir = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $this->own($stage);
        abort_unless($stage->kind === 'open', 422);

        $neighbour = Stage::where('pipeline_id', $stage->pipeline_id)->where('kind', 'open')
            ->where('position', $dir === 'up' ? '<' : '>', $stage->position)
            ->orderBy('position', $dir === 'up' ? 'desc' : 'asc')->first();

        if ($neighbour) {
            [$a, $b] = [$stage->position, $neighbour->position];
            $stage->update(['position' => $b]);
            $neighbour->update(['position' => $a]);
        }

        return back();
    }

    public function deleteStage(Stage $stage): RedirectResponse
    {
        $this->own($stage);
        if ($stage->kind !== 'open') {
            throw ValidationException::withMessages(['stage' => 'Won and Lost are required and cannot be removed.']);
        }
        if ($stage->deals()->exists()) {
            throw ValidationException::withMessages(['stage' => 'Move the deals out of this stage first.']);
        }
        $stage->delete();

        return back()->with('success', 'Stage removed.');
    }

    public function saveTemplate(Request $request, ?MessageTemplate $template = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'language' => ['required', 'string', 'max:10'],
            'category' => ['required', 'in:utility,marketing,authentication'],
            'body' => ['required', 'string', 'max:1024'],
        ]);

        if ($template) {
            $template->update($data);
        } else {
            $number = WhatsAppNumber::firstOrFail();
            MessageTemplate::create($data + ['whatsapp_number_id' => $number->id, 'status' => 'approved', 'variables' => []]);
        }

        return back()->with('success', 'Template saved.');
    }

    public function deleteTemplate(MessageTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Template removed.');
    }

    /** Stages carry no company column; they belong to a pipeline, which is tenant scoped. */
    private function own(Stage $stage): void
    {
        Pipeline::findOrFail($stage->pipeline_id);
    }
}

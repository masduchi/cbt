<?php

namespace App\Http\Controllers;

use App\Models\RunningText;
use App\Support\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RunningTextController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Setting/RunningText', [
            'texts' => RunningText::orderBy('id_text')
                ->get(['id_text', 'text'])
                ->map(fn (RunningText $text) => ['id_text' => (int) $text->id_text, 'text' => (string) $text->text])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:255'],
        ]);

        $text = RunningText::create(['text' => trim($data['text'])]);

        AuditLog::record('setting.running', 'Teks berjalan #'.$text->id_text.' ditambahkan');

        return back()->with('success', 'Teks berjalan ditambahkan.');
    }

    public function update(Request $request, RunningText $text): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:255'],
        ]);

        $text->update(['text' => trim($data['text'])]);

        AuditLog::record('setting.running', 'Teks berjalan #'.$text->id_text.' diperbarui');

        return back()->with('success', 'Teks berjalan diperbarui.');
    }

    public function destroy(RunningText $text): RedirectResponse
    {
        $text->delete();

        AuditLog::record('setting.running', 'Teks berjalan #'.$text->id_text.' dihapus');

        return back()->with('success', 'Teks berjalan dihapus.');
    }
}

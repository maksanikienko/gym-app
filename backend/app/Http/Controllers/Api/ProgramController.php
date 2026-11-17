<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Services\ProgramImporter;
use App\Services\ProgramWriter;
use App\Services\WorkoutPresenter;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request)
    {
        return Program::visibleTo($request->user())
            ->withCount('weeks')
            ->orderByRaw('user_id is null')   // user's own programs first
            ->latest('updated_at')
            ->get()
            ->map(fn ($p) => WorkoutPresenter::programSummary($p));
    }

    public function show(Request $request, Program $program)
    {
        abort_unless($program->isVisibleTo($request->user()), 404);

        return response()->json(WorkoutPresenter::programTree($program));
    }

    public function store(Request $request, ProgramWriter $writer)
    {
        $data = $request->validate(ProgramWriter::rules());
        $program = $writer->save(new Program(['visibility' => 'private', 'source' => 'user']), $data, $request->user());

        return response()->json(WorkoutPresenter::programTree($program), 201);
    }

    public function update(Request $request, Program $program, ProgramWriter $writer)
    {
        // Imported ready-made programs are read-only like templates: copy them to edit.
        abort_unless($program->user_id === $request->user()->id && $program->source !== 'import', 404);
        $data = $request->validate(ProgramWriter::rules());
        $writer->save($program, $data, $request->user());

        return response()->json(WorkoutPresenter::programTree($program->refresh()));
    }

    /** Copy any visible program (e.g. a ready-made template) into the user's own programs. */
    public function copy(Request $request, Program $program, ProgramWriter $writer)
    {
        abort_unless($program->isVisibleTo($request->user()), 404);
        $tree = ProgramWriter::exportTree($program);
        $tree['name_en'] .= ' (copy)';
        if ($tree['name_ru']) {
            $tree['name_ru'] .= ' (копия)';
        }
        $copy = $writer->save(new Program(['visibility' => 'private', 'source' => 'user']), $tree, $request->user());

        return response()->json(WorkoutPresenter::programSummary($copy->loadCount('weeks')), 201);
    }

    public function import(Request $request, ProgramImporter $importer)
    {
        $programs = $importer->import($request->json()->all(), $request->user(), 'private', 'import');

        return response()->json(
            collect($programs)->map(fn ($p) => WorkoutPresenter::programSummary($p->loadCount('weeks'))),
            201
        );
    }

    public function destroy(Request $request, Program $program)
    {
        abort_unless($program->user_id === $request->user()->id, 404);
        $program->delete();

        return response()->noContent();
    }
}

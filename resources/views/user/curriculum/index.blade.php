@extends('layouts.app')

@section('content')
    @include('shared.curriculum.detail', [
        'referee' => auth()->user(),
        'careerData' => $careerData
    ])
@endsection

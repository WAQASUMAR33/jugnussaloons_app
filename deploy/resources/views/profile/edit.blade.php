@extends('layouts.material')

@section('title', 'My Profile Settings')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header Banner -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight heading-font">Profile Settings</h1>
            <p class="text-xs font-semibold text-slate-500 mt-0.5">Manage your account information and login security.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all">
            &larr; Back to Dashboard
        </a>
    </div>

    <!-- Update Profile Info Card -->
    <div class="p-6 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="max-w-xl">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>

    <!-- Update Password Card -->
    <div class="p-6 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="max-w-xl">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    <!-- Delete Account Card -->
    <div class="p-6 bg-white rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="max-w-xl">
            @include('profile.partials.delete-user-form')
        </div>
    </div>

</div>
@endsection

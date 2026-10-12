<form method="post" action="/contact">
@csrf
<input name="name" value="{{ old('name') }}">
@error('name')<p class="error-name">{{ $message }}</p>@enderror
<input name="email" value="{{ old('email') }}">
@error('email', 'ignored-bag')<p class="error-email">{{ $message }}</p>@enderror
@if ($errors->any())<ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<span class="old-message">{{ old('message', 'sem-valor') }}</span>
</form>

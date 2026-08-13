@if(session('yurba_status'))
    <div class="y-alert y-alert--ok">{{ session('yurba_status') }}</div>
@endif
@if($errors->any())
    <div class="y-alert y-alert--err">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

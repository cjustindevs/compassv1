<dialog id="identity-dialog-{{ $referral->id }}" aria-labelledby="identity-title-{{ $referral->id }}" class="m-auto rounded-2xl border-0 p-6 w-full max-w-2xl backdrop:bg-black/40" style="max-height:88dvh;overflow:auto">
    <h2 id="identity-title-{{ $referral->id }}" class="text-xl font-bold">Identity disclosure for referral #{{ $referral->id }}</h2>
    <p class="text-sm my-3">Your identifying information is stored in the separate encrypted Identity Vault. Only authorized personnel involved in referral coordination, professional intervention, or emergency response may access the necessary information. Helpers and moderators cannot view it.</p>
    <form data-identity-form action="{{ route('identity.store',$referral, false) }}" autocomplete="off" class="grid sm:grid-cols-2 gap-3">
        @csrf
        <label class="sm:col-span-2 flex gap-2 text-sm"><input type="checkbox" name="identity_disclosure" value="1" required><span>I voluntarily agree to store my identifying information and allow its authorized disclosure for this referral.</span></label>
        @foreach(\App\Services\IdentityVaultService::FIELDS as $field)
            <label class="text-sm">{{ ucwords(str_replace('_',' ',$field)) }}{{ in_array($field,['real_name','phone_number']) ? ' *' : ' (optional)' }}
                <input name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" maxlength="500" class="block w-full border rounded-lg p-2" @required(in_array($field,['real_name','phone_number']))>
            </label>
        @endforeach
        <p role="alert" data-identity-result class="sm:col-span-2 text-sm text-red-700"></p>
        <div class="sm:col-span-2 flex gap-3 justify-end"><button type="button" class="page-button light" onclick="this.closest('dialog').close()">Not now</button><button type="submit" class="page-button">Submit details securely</button></div>
    </form>
</dialog>
@if((string)(session('identity_referral_id') ?? request('identity')) === (string)$referral->id)
<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('identity-dialog-{{ $referral->id }}').showModal());</script>
@endif
@once
<script>
document.addEventListener('submit',async event=>{
    const form=event.target.closest('[data-identity-form]'); if(!form)return;
    event.preventDefault(); const button=form.querySelector('[type=submit]'), result=form.querySelector('[data-identity-result]');
    button.disabled=true; result.textContent='Saving securely?';
    try {
        const response=await fetch(form.action,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:new FormData(form)});
        const body=await response.json();
        if(!response.ok)throw new Error(body.errors ? Object.values(body.errors).flat().join(' ') : body.message || 'Unable to save. Please try again.');
        form.reset(); result.textContent=body.message; button.hidden=true;
    }catch(error){result.textContent=error.message;button.disabled=false;}
});
</script>
@endonce

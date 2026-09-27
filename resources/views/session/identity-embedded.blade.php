<!doctype html>
<html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Identity disclosure</title></head><body>
@include('partials.referral-identity-modal')
<script>
const modal = document.querySelector('dialog');
modal.showModal();
modal.addEventListener('close', () => parent.postMessage({type:'identity-modal-closed'}, location.origin));
</script></body></html>

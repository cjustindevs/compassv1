<!doctype html>
<html class="compass-ui" lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Identity disclosure</title>    @include('partials.ui-assets')
</head><body>
@include('partials.referral-identity-modal')
<script>
const modal = document.querySelector('dialog');
modal.showModal();
modal.addEventListener('close', () => parent.postMessage({type:'identity-modal-closed'}, location.origin));
</script></body></html>

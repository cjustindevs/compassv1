<!doctype html>
<html class="compass-ui" lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Identity disclosure consent</title>
@php($embeddedScreen = true)
@include('partials.ui-assets')
<style>html,body{margin:0;height:100%;overflow:hidden}*{box-sizing:border-box}</style>
</head><body>
@include('partials.referral-identity-modal', ['embeddedIdentity' => true])
<script>
document.addEventListener('compass-identity-dirty', () => parent.postMessage({type:'identity-form-dirty'}, location.origin));
document.addEventListener('compass-identity-close', () => parent.postMessage({type:'identity-modal-closed'}, location.origin));
document.addEventListener('compass-identity-saved', () => parent.postMessage({type:'identity-details-saved'}, location.origin));
</script></body></html>

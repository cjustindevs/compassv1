(() => {
 document.querySelectorAll('[data-date-range]').forEach(range => {
  const toggle=range.querySelector('[data-range-toggle]'), panel=range.querySelector('.hf-range-panel'), start=range.querySelector('[data-range-start]'), end=range.querySelector('[data-range-end]');
  const close=()=>{panel.hidden=true;toggle.setAttribute('aria-expanded','false');};
  toggle.addEventListener('click',()=>{panel.hidden=!panel.hidden;toggle.setAttribute('aria-expanded',String(!panel.hidden));if(!panel.hidden)start.focus();});
  range.querySelector('[data-range-done]').addEventListener('click',()=>{if(start.reportValidity()&&end.reportValidity()){close();toggle.focus();}});
  const update=()=>{end.min=start.value;if(start.value&&end.value){range.querySelector('[data-range-value]').value=start.value+' to '+end.value;range.querySelector('[data-range-label]').textContent=start.value+' - '+end.value;}};
  start.addEventListener('change',update);end.addEventListener('change',update);end.min=start.value;
  document.addEventListener('click',event=>{if(!range.contains(event.target))close();});
  range.addEventListener('keydown',event=>{if(event.key==='Escape'){close();toggle.focus();}});
 });
})();

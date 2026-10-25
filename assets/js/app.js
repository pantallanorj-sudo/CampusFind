document.querySelectorAll('[data-confirm]').forEach((el)=>el.addEventListener('click',(event)=>{if(!confirm(el.dataset.confirm||'Are you sure?'))event.preventDefault();}));

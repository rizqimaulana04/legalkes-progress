document.querySelectorAll('[data-open]').forEach(button=>button.addEventListener('click',()=>document.getElementById(button.dataset.open)?.showModal()));
document.querySelectorAll('[data-close]').forEach(button=>button.addEventListener('click',()=>button.closest('dialog')?.close()));
document.querySelectorAll('dialog').forEach(dialog=>dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close()}));
const clientBox=document.getElementById('to-client');if(clientBox)clientBox.addEventListener('change',()=>{document.getElementById('client-fields').hidden=!clientBox.checked});

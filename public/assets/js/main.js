// JS global básico
(function(){
  const mobileBtn = document.getElementById('btn-mobile');
  const mobileMenu = document.getElementById('mobile-menu');
  if(mobileBtn){
    mobileBtn.addEventListener('click',()=>{
      mobileMenu.classList.toggle('hidden');
    });
  }
  // Modais
  document.querySelectorAll('[data-modal]')?.forEach(btn=>{
    btn.addEventListener('click',()=>{
      const target = btn.getAttribute('data-modal');
      const el = document.getElementById(`modal-${target}`);
      if(el){ el.classList.remove('hidden'); }
    });
  });
  document.querySelectorAll('[data-close]')?.forEach(btn=>{
    btn.addEventListener('click',()=>{
      const target = btn.getAttribute('data-close');
      const el = document.getElementById(`modal-${target}`);
      if(el){ el.classList.add('hidden'); }
    });
  });
})();

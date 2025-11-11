// Swiper inicial simples
window.addEventListener('DOMContentLoaded',()=>{
  if(window.Swiper){
    new Swiper('.mySwiper',{
      slidesPerView: 2,
      spaceBetween: 12,
      breakpoints:{
        640:{slidesPerView:3},
        768:{slidesPerView:4},
        1024:{slidesPerView:5}
      },
      navigation:{
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev'
      },
      pagination:{ el: '.swiper-pagination', clickable:true }
    });
  }
});

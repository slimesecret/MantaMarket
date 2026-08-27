/* ══════════════════════════════════════════════
   bg.js — Slider banner + Danh mục scroll ngang
   ══════════════════════════════════════════════ */

/* ── ĐỔI LOGO KHI SCROLL ──
   Logo sáng → logo tối khi cuộn qua 50px */
const logo = document.getElementById("logo");

window.addEventListener("scroll", function () {
    if (window.scrollY > 50) {
        logo.src = "../img/footer-logo.png";
    } else {
        logo.src = "../img/new-logo.png";
    }
});


/* ══════════════════════════════════════════════
   SLIDER BANNER QUẢNG CÁO
   Dùng cho: .slider > .slides > .slide
   ══════════════════════════════════════════════ */
let index = 0;
const slides    = document.querySelectorAll(".slide");
const slider    = document.querySelector(".slides");
const dots      = document.querySelectorAll(".dot");
const container = document.querySelector(".slider");
const total     = slides.length;
let autoSlide;

/* Chuyển đến slide thứ i, cập nhật dot active */
function showSlide(i) {
    slider.style.transform = `translateX(-${i * 100}%)`;
    dots.forEach(d => d.classList.remove("active"));
    dots[i].classList.add("active");
}

/* Nút next / prev */
document.querySelector(".next").onclick = () => { index = (index + 1) % total;            showSlide(index); resetAuto(); };
document.querySelector(".prev").onclick = () => { index = (index - 1 + total) % total;    showSlide(index); resetAuto(); };

/* Click dot để nhảy thẳng đến slide */
dots.forEach((dot, i) => {
    dot.onclick = () => { index = i; showSlide(index); resetAuto(); };
});

/* Auto-play mỗi 3 giây; dừng khi hover */
function startAuto() { autoSlide = setInterval(() => { index = (index + 1) % total; showSlide(index); }, 3000); }
function stopAuto()  { clearInterval(autoSlide); }
function resetAuto() { stopAuto(); startAuto(); }

container.addEventListener("mouseenter", stopAuto);
container.addEventListener("mouseleave", startAuto);
startAuto();


/* ══════════════════════════════════════════════
   DANH MỤC — SCROLL NGANG
   Dùng cho: #categoriesTrack bên trong .categories-track-clip
   Hiện/ẩn mũi tên prev/next theo vị trí hiện tại
   ══════════════════════════════════════════════ */
const clip  = document.querySelector('.categories-track-clip');
const track = document.getElementById('categoriesTrack');
const prev  = document.getElementById('arrowPrev');
const next  = document.getElementById('arrowNext');

const COLS_PER_VIEW = 10;                         // số cột hiện cùng lúc
const TOTAL_COLS    = Math.ceil(27 / 2);          // tổng cột (27 items, 2 hàng → 14 cột)
const MAX_SLIDE     = TOTAL_COLS - COLS_PER_VIEW; // số bước trượt tối đa

let currentCol = 0;

/* Trượt đến cột col, cập nhật trạng thái mũi tên */
function goTo(col) {
    currentCol = Math.max(0, Math.min(col, MAX_SLIDE));
    const contentWidth = clip.offsetWidth - 12;       // trừ padding 6px × 2
    const colWidth     = (contentWidth - 9 * 6) / 10; // độ rộng 1 cột
    const slideAmount  = colWidth + 6;                 // cột + gap

    track.style.transform = `translateX(-${currentCol * slideAmount}px)`;
    prev.classList.toggle('hidden', currentCol === 0);
    next.classList.toggle('hidden', currentCol >= MAX_SLIDE);
}

prev.addEventListener('click', () => goTo(currentCol - COLS_PER_VIEW));
next.addEventListener('click', () => goTo(currentCol + COLS_PER_VIEW));

goTo(0); // khởi tạo
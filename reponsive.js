function toggleMenu(event) {
    event.preventDefault(); // Ngăn chặn reload trang
    let submenu = event.target.nextElementSibling;

    if (submenu && submenu.classList.contains("submenu")) {
        submenu.classList.toggle("active");

        // Ẩn các menu khác khi mở menu mới
        document.querySelectorAll(".submenu").forEach(menu => {
            if (menu !== submenu) {
                menu.classList.remove("active");
            }
        });
    }
}

// Đợi trang tải xong rồi mới gán sự kiện
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".dropdown > a").forEach(link => {
        link.addEventListener("click", toggleMenu);
    });
});

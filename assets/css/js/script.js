function toggleMenu(event) {
    event.preventDefault(); // Ngăn chặn reload trang khi click
    let submenu = event.target.nextElementSibling;

    // Kiểm tra nếu submenu tồn tại
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

// Gán sự kiện cho các menu dropdown
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".dropdown > a").forEach(link => {
        link.addEventListener("click", toggleMenu);
    });
});
s

// theem thanh dang nhap


// Toggle hiển thị menu dropdown
function toggleDropdown(event, menuId) {
    event.stopPropagation(); // Ngăn chặn đóng menu ngay khi bấm

    // Đóng tất cả các menu trước khi mở menu mới
    document.querySelectorAll(".submenu").forEach(menu => {
        if (menu.id !== menuId) {
            menu.classList.remove("active");
        }
    });

    // Hiển thị menu
    let menu = document.getElementById(menuId);
    menu.classList.toggle("active");
}

// Sự kiện click vào icon
document.getElementById("bell-icon").addEventListener("click", (e) => {
    toggleDropdown(e, "notifications");
});

document.getElementById("help-icon").addEventListener("click", (e) => {
    toggleDropdown(e, "help-menu");
});

document.getElementById("user-icon").addEventListener("click", (e) => {
    toggleDropdown(e, "user-menu");
});

// Đóng menu khi click ra ngoài
document.addEventListener("click", function () {
    document.querySelectorAll(".submenu").forEach(menu => {
        menu.classList.remove("active");
    });
});

// Xử lý sự kiện đăng xuất
document.getElementById("logout").addEventListener("click", function () {
    alert("Bạn đã đăng xuất thành công!");
    window.location.href = "login.html"; // Chuyển hướng về trang đăng nhập
});

// Profile Dropdown Toggle (يدعم الهيدر والشريط الجانبي للجوال)
document.addEventListener('DOMContentLoaded', function () {
    const dropdowns = document.querySelectorAll('.profile-dropdown');
    dropdowns.forEach(function (profileDropdown) {
        const profileAvatar = profileDropdown.querySelector('.profile-avatar');
        const dropdownMenu = profileDropdown.querySelector('.dropdown-menu');
        if (!profileAvatar) return;

        profileAvatar.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('active');
        });

        if (dropdownMenu) {
            dropdownMenu.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    });

    document.addEventListener('click', function (e) {
        dropdowns.forEach(function (profileDropdown) {
            if (!profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });
    });
});

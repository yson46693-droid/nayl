// Profile Dropdown Toggle
document.addEventListener('DOMContentLoaded', function () {
    const profileDropdown = document.querySelector('.profile-dropdown');
    const profileAvatar = document.querySelector('.profile-avatar');

    if (profileAvatar && profileDropdown) {
        profileAvatar.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('active');
        });

        document.addEventListener('click', function (e) {
            if (!profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });

        const dropdownMenu = profileDropdown.querySelector('.dropdown-menu');
        if (dropdownMenu) {
            dropdownMenu.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    }
});

// Profile Dropdown Toggle
document.addEventListener('DOMContentLoaded', function () {
    const profileDropdown = document.querySelector('.profile-dropdown');
    const profileAvatar = document.querySelector('.profile-avatar');

    if (profileAvatar && profileDropdown) {
        // Toggle dropdown on avatar click
        profileAvatar.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });

        // Prevent dropdown from closing when clicking inside it
        const dropdownMenu = document.querySelector('.dropdown-menu');
        if (dropdownMenu) {
            dropdownMenu.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    }
});

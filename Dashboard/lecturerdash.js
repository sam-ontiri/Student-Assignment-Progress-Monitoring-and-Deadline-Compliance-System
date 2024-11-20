let btn = document.querySelector('#btn');
let sidebar = document.querySelector('.sidebar');
btn.onclick = function () {
    sidebar.classList.toggle('active');
};

document.getElementById('notificationForm').addEventListener('submit', function(event) {
    event.preventDefault(); // Prevent the default form submission

    const formData = new FormData(this); // Get form data

    fetch('send_notification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message); // Notify the user of success
            document.getElementById('notificationForm').reset(); // Reset the form
        } else {
            alert(data.message); // Notify the user of error
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while sending the notification.');
    });
});

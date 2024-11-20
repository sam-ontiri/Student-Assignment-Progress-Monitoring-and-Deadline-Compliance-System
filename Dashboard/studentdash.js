let btn = document.querySelector('#btn');
let sidebar = document.querySelector('.sidebar');
btn.onclick = function () {
    sidebar.classList.toggle('active');
};


document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var assignments = JSON.parse(document.getElementById('assignments-data').textContent);

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: assignments.map(function(assignment) {
            return {
                title: assignment.title + ' (' + assignment.course_name + ')',
                start: assignment.due_date,
                allDay: true,
                color: getRandomColor()
            };
        }),
        eventClick: function(info) {
            alert('Assignment: ' + info.event.title + '\nDue Date: ' + info.event.start.toDateString());
        },
        validRange: function(nowDate) {
            return {
                start: nowDate
            };
        }
    });

    calendar.render();

    function getRandomColor() {
        var letters = '0123456789ABCDEF';
        var color = '#';
        for (var i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    }
});
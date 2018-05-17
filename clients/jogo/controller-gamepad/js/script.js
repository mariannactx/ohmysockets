var cornerSound = new Audio('sounds/click.mp3');
window.addEventListener("load", function(){

    var classes = ["corner", "cross"];
    for(var classKey in classes){
        var btns = document.getElementsByClassName(classes[classKey]);

        for( var key in btns) {
            var btn = btns[key];

            if (!isNaN(key)) {
                var events = ["touchstart", "touchend"];
                for(var eventKey in events){
                    btn.addEventListener(events[eventKey], function () {
                        cornerSound.play();
                    });
                }
            }
        }
    };

    ["left", "right", "jump"].forEach(function(id){
        var btn = document.getElementById(id);
        console.log(id, btn);
        btn.addEventListener("touchstart", function (e) {
            action(e, id, "true");
        });
        btn.addEventListener("touchend", function (e) {
            action(e, id, "false");
        });
    });

    initPlayer();
});

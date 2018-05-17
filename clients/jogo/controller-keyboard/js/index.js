function getKey (e) {
    var code = e.keyCode || e.which;

    return document.querySelector(
        '[data-key="' + code + '"],'
      + '[data-char*="' + encodeURIComponent(String.fromCharCode(code)) + '"]'
    );
}

function size () {
    var keyboard = document.getElementById('keyboard');
    var size = keyboard.parentNode.clientWidth / 90;
    keyboard.style.fontSize = size + 'px';
}

window.addEventListener("load", function(){
    size();

    document.addEventListener('keydown', function(ev) {
        return onkey(ev, ev.keyCode, true);
    }, false);

    document.addEventListener('keyup', function(ev) {
        return onkey(ev, ev.keyCode, false);
    }, false);

    initPlayer();
});

KEY = { SPACE: 32, LEFT: 37, A: 65, D:68, UP: 38, RIGHT: 39, DOWN: 40 };
function onkey(ev, key, down) {
    var direction;

    switch(key) {
        case KEY.LEFT:
        case KEY.A:
            direction = "left";
            break;
        case KEY.RIGHT:
        case KEY.D:
            direction = "right";
            break;
        case KEY.SPACE:
            direction = "jump";
            break;
    }

    if(direction) {
        action(ev, direction, down);
    }

    ev.preventDefault();
    return false;
}

document.body.addEventListener('keydown', function (e) {
    var key = getKey(e);

    if (!key) {
        return console.warn('No key for', e.keyCode);
    }

    key.setAttribute('data-pressed', 'on');
});

document.body.addEventListener('keyup', function (e) {
    var key = getKey(e);
    key && key.removeAttribute('data-pressed');
});

window.addEventListener('resize', function (e) {
    size();
});
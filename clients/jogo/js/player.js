function send(message){
    socket.send(JSON.stringify(message));
}

function action(ev, key, down){
    send({
        type: "move",
        key: key,
        down: down
    });
}

function initPlayer() {

    try {

        socket = new WebSocket(host);

        socket.onopen = function(ev) {};
        socket.onclose = function(ev) {};

        socket.onmessage = function(ev) {

            msg = JSON.parse(ev.data);

            if(msg.type == "connected"){

                localStorage.user = msg.user;

                send({
                   type: "new-player",
                   user: msg.user,
                   name: localStorage.apelido,
                   color: localStorage.cor
                });

                var screen = document.getElementById("game-and-watch-game");
                if(screen){
                    var text = "<p style='color: rgb(" + localStorage.cor + ")'>";
                        text += " @" + localStorage.apelido;
                        text += "</p>";

                    screen.innerHTML = text;
                }
            }
        };


    } catch(ex){ console.log(ex); }

}

function choosePlayer(tipo){
    var form = document.getElementById("form-player");
    form.action = "controller-" + tipo;

    var modalContainer = document.getElementsByClassName("modal-container")[0];
    modalContainer.style.display = "block";
}

function newPlayer(form){
    localStorage.apelido = form.apelido.value;
    localStorage.cor = form.cor.value;
}
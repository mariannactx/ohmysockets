function init() {
    try {
        socket = new WebSocket(host);
        socket.onopen    = function(ev) {};
        socket.onclose   = function(ev) {};
        socket.onmessage = function(ev) {
            msg = JSON.parse(ev.data);

            if(msg.type == "connected"){
                socket.send('{"type": "new"}');
            }
        }

        socket.onclose   = function(ev) {}

    } catch(ex){ log("chat", ex); }

}

function change(face){
    socket.send('{"type": "change", "face": "'+face+'"}');
}

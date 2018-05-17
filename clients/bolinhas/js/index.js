function init() {
    try {
        socket = new WebSocket(host);
        socket.onopen    = function(msg) {}
        socket.onmessage = function(msg) {
            msg = JSON.parse(msg.data);

            if(msg.type == "connected"){
                socket.send('{"type": "new"}');
            }
        }

        socket.onclose   = function(msg) {}

    } catch(ex){ log("chat", ex); }

}

function change(face){
    socket.send('{"type": "change", "face": "'+face+'"}');
}

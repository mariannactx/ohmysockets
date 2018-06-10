var places = [
    {top: "10px", left:"10px"},
    {top: "100px", left:"100px"},
    {top: "10px", left:"200px"},
    {top: "100px", left:"300px"},
    {top: "10px", left:"400px"},
    {top: "100px", left:"500px"},
    {top: "10px", left:"600px"},
    {top: "100px", left:"700px"},
    {top: "10px", left:"800px"}
];

function init() {
    try {
        socket = new WebSocket(host);

        socket.onopen    = function(ev) {};
        socket.onclose   = function(ev) {};
        socket.onmessage = function(ev) {
            msg = JSON.parse(ev.data);

            switch(msg.type){
                case "change": changeFace(msg.user, msg.face); break;
                case "new": newFace(msg.user); break;
                case "disconnected": removeFace(msg.user); break;
            }

        };
    } catch(ex){ log("chat", ex); }

}

function newFace(id){

    var ball = document.createElement("div");

    var ballPosition = "top:"+places[0].top+"; left:"+places[0].left;
    ball.innerHTML = ballStructure(ballPosition, id);

    byId("allWrapper").appendChild(ball);
    places.splice(0, 1);

    changeFace(id, "happy");
}

function ballStructure(position, id){
    return "<div id='ballWrapper' class='bounce' style='"+position+"'><div id='"+ id +"'>" +
    "        <div></div>" +
    "        <div class='bloom'>" +
    "            <div class='sparkle'>" +
    "                <div class='sparkle-line'></div>" +
    "                <div class='sparkle-line'></div>" +
    "                <div class='sparkle-line'></div>" +
    "                <div class='sparkle-line'></div>" +
    "</div></div></div>";
}

function removeFace(id){
    var filha = byId(id);
    var mae = filha.parentNode;
    var avo = mae.parentNode;

    places.push({
        top:  mae.style.top,
        left: mae.style.left
    });

    byId('allWrapper').removeChild(avo);
}

function changeFace(ball, face){
    byId(ball).setAttribute("class", face);
    byId(ball).innerHTML = faces[face];
    byId(ball).children[0].setAttribute("class", face);
}

function byId(id){
    return document.getElementById(id);
}

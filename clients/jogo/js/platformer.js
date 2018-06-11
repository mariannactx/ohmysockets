//-------------------------------------------------------------------------
// Multiplayer
//-------------------------------------------------------------------------

window.addEventListener("load", function(){
    initGame();
});

function initGame() {
    try {
        socket = new WebSocket(host);

        socket.onopen    = function(ev) {};
        socket.onclose   = function(ev) {};
        socket.onmessage = function(ev) {
            msg = JSON.parse(ev.data);

            switch (msg.type){
                case "new-player": newPlayer(msg.user, msg.name, msg.color); break;
                case "move": move(msg.user, msg.key, msg.down); break;
                case "disconnected": killPlayer(players[msg.user]); break;
            }
        };

    } catch(ex){ console.log(ex); }

}

function newPlayer(id, name, color){

    players[id] = setupEntity({
        "height":32,
        "properties":
            {
                name: name,
                color: "rgb(" + color + ")"
            },
        "type":"player",
        "visible":true,
        "width":32,
        "x":96,
        "y":480
    });

}

function move(playerId, key, down) {
    players[playerId][key] = down;
}


  //-------------------------------------------------------------------------
  // POLYFILLS
  //-------------------------------------------------------------------------
  
  if (!window.requestAnimationFrame) { // http://paulirish.com/2011/requestanimationframe-for-smart-animating/
    window.requestAnimationFrame = window.webkitRequestAnimationFrame || 
                                   window.mozRequestAnimationFrame    || 
                                   window.oRequestAnimationFrame      || 
                                   window.msRequestAnimationFrame     || 
                                   function(callback, element) {
                                     window.setTimeout(callback, 1000 / 60);
                                   }
  }

  //-------------------------------------------------------------------------
  // UTILITIES
  //-------------------------------------------------------------------------
  
  function timestamp() {
    return window.performance && window.performance.now ? window.performance.now() : new Date().getTime();
  }
  
  function bound(x, min, max) {
    return Math.max(min, Math.min(max, x));
  }

  function overlap(x1, y1, w1, h1, x2, y2, w2, h2) {
    return !(((x1 + w1 - 1) < x2) ||
             ((x2 + w2 - 1) < x1) ||
             ((y1 + h1 - 1) < y2) ||
             ((y2 + h2 - 1) < y1))
  }
  
  //-------------------------------------------------------------------------
  // GAME CONSTANTS AND VARIABLES
  //-------------------------------------------------------------------------
  
  var MAP      = { tw: 64, th: 48 },
      TILE     = 32,
      METER    = TILE,
      GRAVITY  = 9.8 * 6, // default (exagerated) gravity
      MAXDX    = 15,      // default max horizontal speed (15 tiles per second)
      MAXDY    = 60,      // default max vertical speed   (60 tiles per second)
      ACCEL    = 1/2,     // default take 1/2 second to reach maxdx (horizontal acceleration)
      FRICTION = 1/6,     // default take 1/6 second to stop from maxdx (horizontal friction)
      IMPULSE  = 1500,    // default player jump impulse
      COLOR    = { BLACK: '#000000', YELLOW: '#ECD078', BRICK: '#D95B43', PINK: '#C02942', PURPLE: '#542437', GREY: '#333', SLATE: '#53777A', GOLD: 'gold' },
      COLORS   = [ COLOR.YELLOW, COLOR.BRICK, COLOR.PINK, COLOR.PURPLE, COLOR.GREY ],
      KEY      = { SPACE: 32, LEFT: 37, UP: 38, RIGHT: 39, DOWN: 40 };
      
  var fps      = 60,
      step     = 1/fps,
      canvas   = document.getElementById('canvas'),
      ctx      = canvas.getContext('2d'),
      width    = canvas.width  = MAP.tw * TILE,
      height   = canvas.height = MAP.th * TILE,
      players  = [],
      monsters = [],
      treasures = [],
      cells    = [];
  
  var t2p      = function(t)     { return t*TILE;                  },
      p2t      = function(p)     { return Math.floor(p/TILE);      },
      cell     = function(x,y)   { return tcell(p2t(x),p2t(y));    },
      tcell    = function(tx,ty) { return cells[tx + (ty*MAP.tw)]; };
  
  
  //-------------------------------------------------------------------------
  // UPDATE LOOP
  //-------------------------------------------------------------------------

  function update(dt) {
    updatePlayers(dt);
    updateMonsters(dt);
    checkTreasure();
  }

  function updatePlayers(dt) {
      var player; 
      for(var n in players){
        player = players[n];

        if (!player.dead) {
          updateEntity(player, dt);
          var monster;
          for(var m in monsters){
              monster = monsters[m];
              if (overlap(player.x, player.y, TILE, TILE, monster.x, monster.y, TILE, TILE))
                  if ((player.dy > 0) && (monster.y - player.y > TILE / 2))
                      killMonster(monster);
          }
        }
      }
  }

  function updateMonsters(dt) {
      var monster;
      for(var m in monsters){
          monster = monsters[m];
          if (!monster.dead) {
              updateEntity(monster, dt);
              var player;
              for(var n in players){
                  player = players[n];
                  if (overlap(player.x, player.y, TILE, TILE, monster.x, monster.y, TILE, TILE)) {
                      if ((player.dy > 0) && (monster.y - player.y > TILE / 2))
                          killMonster(monster);
                      else
                          killPlayer(player);
                  }
              }
          }
      }
  }

  function checkTreasure() {
    var t;

    for(var n in treasures) {
      t = treasures[n];

      var pn, p;
      for(var pn in players){
        p = players[pn] ;
        if (!t.collected && overlap(p.x, p.y, TILE, TILE, t.x, t.y, TILE, TILE)) {
            p.collected++;
            t.collected = true;
        }
      }
    }

  }

  function killMonster(monster, player) {
    if(player) {
        player.killed++;
    }

    monster.dead = true;
  }

  function killPlayer(player) {
    player.dead = true;
  }

  function updateEntity(entity, dt) {
    var wasleft    = entity.dx  < 0,
        wasright   = entity.dx  > 0,
        falling    = entity.falling,
        friction   = entity.friction * (falling ? 0.5 : 1),
        accel      = entity.accel    * (falling ? 0.5 : 1);
  
    entity.ddx = 0;
    entity.ddy = entity.gravity;
  
    if (entity.left)
      entity.ddx = entity.ddx - accel;
    else if (wasleft)
      entity.ddx = entity.ddx + friction;
  
    if (entity.right)
      entity.ddx = entity.ddx + accel;
    else if (wasright)
      entity.ddx = entity.ddx - friction;

    if (entity.jump && !entity.jumping && !falling) {
      entity.ddy = entity.ddy - entity.impulse; // an instant big force impulse
      entity.jumping = true;
    }
  
    entity.x  = entity.x  + (dt * entity.dx);
    entity.y  = entity.y  + (dt * entity.dy);
    entity.dx = bound(entity.dx + (dt * entity.ddx), -entity.maxdx, entity.maxdx);
    entity.dy = bound(entity.dy + (dt * entity.ddy), -entity.maxdy, entity.maxdy);
  
    if ((wasleft  && (entity.dx > 0)) ||
        (wasright && (entity.dx < 0))) {
      entity.dx = 0; // clamp at zero to prevent friction from making us jiggle side to side
    }
  
    var tx        = p2t(entity.x),
        ty        = p2t(entity.y),
        nx        = entity.x%TILE,
        ny        = entity.y%TILE,
        cell      = tcell(tx,     ty),
        cellright = tcell(tx + 1, ty),
        celldown  = tcell(tx,     ty + 1),
        celldiag  = tcell(tx + 1, ty + 1);
  
    if (entity.dy > 0) {
      
      if ((celldown && !cell) ||
          (celldiag && !cellright && nx)) {
        entity.y = t2p(ty);
        entity.dy = 0;
        entity.falling = false;
        entity.jumping = false;
        ny = 0;
      }
    }
    else if (entity.dy < 0) {
      if ((cell      && !celldown) ||
          (cellright && !celldiag && nx)) {
        entity.y = t2p(ty + 1);
        entity.dy = 0;
        cell      = celldown;
        cellright = celldiag;
        ny        = 0;
      }
    }
  
    if (entity.dx > 0) {
      if ((cellright && !cell) ||
          (celldiag  && !celldown && ny)) {
        entity.x = t2p(tx);
        entity.dx = 0;
      }
    }
    else if (entity.dx < 0) {
      if ((cell     && !cellright) ||
          (celldown && !celldiag && ny)) {
        entity.x = t2p(tx + 1);
        entity.dx = 0;
      }
    }

    if (entity.monster) {
      if (entity.left && (cell || !celldown)) {
        entity.left = false;
        entity.right = true;
      }      
      else if (entity.right && (cellright || !celldiag)) {
        entity.right = false;
        entity.left  = true;
      }
    }
  
    entity.falling = ! (celldown || (nx && celldiag));
  
  }

  //-------------------------------------------------------------------------
  // RENDERING
  //-------------------------------------------------------------------------
  
  function render(ctx, frame, dt) {
    ctx.clearRect(0, 0, width, height);

    renderMap(ctx);
    renderTreasure(ctx, frame);

    if(!isEmpty(players))
    renderChars(false, players, ctx, dt);

    renderChars(COLOR.SLATE, monsters, ctx, dt);
  }

  function isEmpty(obj) {
    for(var prop in obj) {
        if(obj.hasOwnProperty(prop))
            return false;
    }

    return true;
  }

  function renderMap(ctx) {
    var x, y, cell;
    for(y = 0 ; y < MAP.th ; y++) {
      for(x = 0 ; x < MAP.tw ; x++) {
        cell = tcell(x, y);
        if (cell) {
          ctx.fillStyle = COLORS[cell - 1];
          ctx.fillRect(x * TILE, y * TILE, TILE, TILE);
        }
      }
    }
  }

  function renderChars(color, chars, ctx, dt){
    var n, max, char;
      for(var n in chars){
      char = chars[n];
      if (!char.dead) {
        var x = char.x + (char.dx * dt);
        var y = char.y + (char.dy * dt);

          ctx.fillStyle = color ? color : char.color;
          ctx.fillRect(x, y, TILE, TILE);
          ctx.font = "60pt Lato";
          if(char.player) {
            ctx.fillText(char.name, (x - 8),(y - 10));
          }
      }
    }
  }

  function renderTreasure(ctx, frame) {
    ctx.fillStyle   = COLOR.GOLD;
    ctx.globalAlpha = 0.25 + tweenTreasure(frame, 60);
    var n, max, t;
    for(n = 0, max = treasures.length ; n < max ; n++) {
      t = treasures[n];
      if (!t.collected)
        ctx.fillRect(t.x, t.y + TILE/3, TILE, TILE*2/3);
    }
    ctx.globalAlpha = 1;
  }

  function tweenTreasure(frame, duration) {
    var half  = duration/2
        pulse = frame%duration;
    return pulse < half ? (pulse/half) : 1-(pulse-half)/half;
  }

  //-------------------------------------------------------------------------
  // LOAD THE MAP
  //-------------------------------------------------------------------------

  function get(url, onsuccess) {
      var request = new XMLHttpRequest();
      request.onreadystatechange = function() {
          if ((request.readyState == 4) && (request.status == 200))
              onsuccess(request);
      }

      request.open("GET", url, true);
      request.send();
  }

  get("js/level.json", function(request) {
      setup(JSON.parse(request.responseText));
      frame();
  });

  function setup(map) {
    var data    = map.layers[0].data,
        objects = map.layers[1].objects,
        n, obj, entity;

    for(n = 0 ; n < objects.length ; n++) {
      obj = objects[n];
      entity = setupEntity(obj);
      switch(obj.type) {
        case "player"   : players.push(entity);  break;
        case "monster"  : monsters.push(entity); break;
        case "treasure" : treasures.push(entity); break;
      }
    }

    cells = data;
  }

  function setupEntity(obj) {
    var entity = {};
    entity.x        = obj.x;
    entity.y        = obj.y;
    entity.dx       = 0;
    entity.dy       = 0;
    entity.monster  = obj.type == "monster";
    entity.player   = obj.type == "player";
    entity.treasure = obj.type == "treasure";
    entity.start    = { x: obj.x, y: obj.y }
    entity.killed   = entity.collected = 0;

    if(obj.properties) {
        entity.gravity = METER * (obj.properties.gravity || GRAVITY);
        entity.maxdx = METER * (obj.properties.maxdx || MAXDX);
        entity.maxdy = METER * (obj.properties.maxdy || MAXDY);
        entity.impulse = METER * (obj.properties.impulse || IMPULSE);
        entity.accel = entity.maxdx / (obj.properties.accel || ACCEL);
        entity.friction = entity.maxdx / (obj.properties.friction || FRICTION);
        entity.left     = obj.properties.left;
        entity.right    = obj.properties.right;
        entity.name     = obj.properties.name;
        entity.color    = obj.properties.color;
    }

    return entity;
  }

  //-------------------------------------------------------------------------
  // THE GAME LOOP
  //-------------------------------------------------------------------------
  
  var counter = 0, dt = 0, now,
      last = timestamp();
  
  function frame() {
    now = timestamp();
    dt = dt + Math.min(1, (now - last) / 1000);
    while(dt > step) {
      dt = dt - step;
      update(step);
    }
    render(ctx, counter, dt);
    last = now;
    counter++;
    requestAnimationFrame(frame, canvas);
  }


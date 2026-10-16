$(function () {
  "use strict";

  var t;
  var o = $("html").attr("data-textdirection") === "rtl";

  // Tipos de notificación
  var n = $("#type-success");
  var i = $("#type-info");
  var s = $("#type-warning");
  var a = $("#type-error");

  // Posiciones
  var e = $("#position-top-left");
  var r = $("#position-top-center");
  var c = $("#position-top-right");
  var l = $("#position-top-full");
  var u = $("#position-bottom-left");
  var h = $("#position-bottom-center");
  var f = $("#position-bottom-right");
  var m = $("#position-bottom-full");

  // Otras opciones
  var d = $("#text-notification");
  var k = $("#close-button");
  var p = $("#progress-bar");
  var w = $("#clear-toast-btn");
  var y = $("#show-remove-toast");
  var g = $("#remove-toast");
  var b = $("#show-clear-toast");
  var v = $("#clear-toast");
  var B = $("#fast-duration");
  var I = $("#slow-duration");
  var M = $("#timeout");
  var T = $("#sticky");
  var C = $("#slide-toast");
  var D = $("#fade-toast");

  // Tipos
  n.on("click", function () {
    toastr.success("Have fun storming the castle!", "Miracle Max Says", {
      closeButton: true,
      tapToDismiss: false,
      rtl: o,
    });
  });

  i.on("click", function () {
    toastr.info("We do have the Kapua suite available.", "Turtle Bay Resort", {
      closeButton: true,
      tapToDismiss: false,
      rtl: o,
    });
  });

  s.on("click", function () {
    toastr.warning(
      "My name is Inigo Montoya. You killed my father, prepare to die!",
      {
        closeButton: true,
        tapToDismiss: false,
        rtl: o,
      }
    );
  });

  a.on("click", function () {
    toastr.error(
      "I do not think that word means what you think it means.",
      "Inconceivable!",
      {
        closeButton: true,
        tapToDismiss: false,
        rtl: o,
      }
    );
  });

  // Posiciones
  e.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Top Left!",
      { positionClass: "toast-top-left", rtl: o }
    );
  });

  r.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Top Center!",
      { positionClass: "toast-top-center", rtl: o }
    );
  });

  c.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Top Right!",
      { positionClass: "toast-top-right", rtl: o }
    );
  });

  l.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Top Full Width!",
      { positionClass: "toast-top-full-width", rtl: o }
    );
  });

  u.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Bottom Left!",
      { positionClass: "toast-bottom-left", rtl: o }
    );
  });

  h.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Bottom Center!",
      { positionClass: "toast-bottom-center", rtl: o }
    );
  });

  f.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Bottom Right!",
      { positionClass: "toast-bottom-right", rtl: o }
    );
  });

  m.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Bottom Full Width!",
      { positionClass: "toast-bottom-full-width", rtl: o }
    );
  });

  // Texto simple
  d.on("click", function () {
    toastr.info("Have fun storming the castle!", "Miracle Max Says", {
      rtl: o,
    });
  });

  // Con botón de cierre
  k.on("click", function () {
    toastr.success("Have fun storming the castle!", "With Close Button", {
      closeButton: true,
      rtl: o,
    });
  });

  // Con barra de progreso
  p.on("click", function () {
    toastr.success("Have fun storming the castle!", "Progress Bar", {
      closeButton: true,
      tapToDismiss: false,
      progressBar: true,
      rtl: o,
    });
  });

  // Toast que se puede eliminar a sí mismo
  w.on("click", function () {
    t =
      t ||
      toastr.info(
        'Clear itself?<br /><br /><button type="button" class="btn btn-primary clear">Yes</button>',
        "Clear Toast Button",
        {
          closeButton: true,
          timeOut: 0,
          extendedTimeOut: 0,
          tapToDismiss: false,
          rtl: o,
        }
      );

    if (t.find(".clear").length) {
      t.delegate(".clear", "click", function () {
        toastr.clear(t, { force: true });
        t = undefined;
      });
    }
  });

  // Eliminar individual
  y.on("click", function () {
    toastr.info("Have fun storming the castle!", "Miracle Max Says", {
      rtl: o,
    });
  });

  g.on("click", function () {
    toastr.remove();
  });

  // Limpiar todos
  b.on("click", function () {
    toastr.info("Have fun storming the castle!", "Miracle Max Says", {
      rtl: o,
    });
  });

  v.on("click", function () {
    toastr.clear();
  });

  // Velocidades
  B.on("click", function () {
    toastr.success("Have fun storming the castle!", "Fast Duration", {
      showDuration: 500,
      rtl: o,
    });
  });

  I.on("click", function () {
    toastr.warning("Have fun storming the castle!", "Slow Duration", {
      hideDuration: 3000,
      rtl: o,
    });
  });

  // Timeout y Sticky
  M.on("click", function () {
    toastr.error(
      "I do not think that word means what you think it means.",
      "Timeout!",
      { timeOut: 5000, rtl: o }
    );
  });

  T.on("click", function () {
    toastr.info(
      "I do not think that word means what you think it means.",
      "Sticky!",
      { timeOut: 0, rtl: o }
    );
  });

  // Animaciones
  C.on("click", function () {
    toastr.success(
      "I do not think that word means what you think it means.",
      "Slide Down / Slide Up!",
      { showMethod: "slideDown", hideMethod: "slideUp", timeOut: 2000, rtl: o }
    );
  });

  D.on("click", function () {
    toastr.success(
      "I do not think that word means what you think it means.",
      "Fade In / Fade Out!",
      { showMethod: "fadeIn", hideMethod: "fadeOut", timeOut: 2000, rtl: o }
    );
  });
});
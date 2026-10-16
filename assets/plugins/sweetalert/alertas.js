$(document).ready(function () {
  // Básicos
  $("#basic-alert").on("click", function () {
    Swal.fire({
      title: "Any fool can use a computer",
      confirmButtonClass: "btn btn-primary",
      buttonsStyling: false,
    });
  });

  $("#with-title").on("click", function () {
    Swal.fire({
      title: "The Internet?,",
      text: "That thing is still around?",
      confirmButtonClass: "btn btn-primary",
      buttonsStyling: false,
    });
  });

  $("#footer-alert").on("click", function () {
    Swal.fire({
      icon: "error",
      title: "Oops...",
      text: "Something went wrong!",
      footer: "<a href>Why do I have this issue?</a>",
      confirmButtonClass: "btn btn-primary",
      buttonsStyling: false,
    });
  });

  $("#html-alert").on("click", function () {
    Swal.fire({
      title: "<strong>HTML <u>example</u></strong>",
      icon: "info",
      html: 'You can use <b>bold text</b>, <a href="https://pixinvent.com/" target="_blank">links</a> and other HTML tags',
      showCloseButton: true,
      showCancelButton: true,
      focusConfirm: false,
      confirmButtonText: '<i class="fa fa-thumbs-up"></i> Great!',
      confirmButtonAriaLabel: "Thumbs up, great!",
      cancelButtonText: '<i class="fa fa-thumbs-down"></i>',
      cancelButtonAriaLabel: "Thumbs down",
      confirmButtonClass: "btn btn-primary",
      cancelButtonClass: "btn btn-danger ml-1",
      buttonsStyling: false,
    });
  });

  // Posiciones
  const positions = [
    { id: "#position-top-start", position: "top-start" },
    { id: "#position-top-end", position: "top-end" },
    { id: "#position-bottom-start", position: "bottom-start" },
    { id: "#position-bottom-end", position: "bottom-end" },
  ];

  positions.forEach(({ id, position }) => {
    $(id).on("click", function () {
      Swal.fire({
        position,
        icon: "success",
        title: "Your work has been saved",
        showConfirmButton: false,
        timer: 1500,
        confirmButtonClass: "btn btn-primary",
        buttonsStyling: false,
      });
    });
  });

  // Animaciones
  const animations = [
    { id: "#bounce-in-animation", class: "bounceIn" },
    { id: "#fade-in-animation", class: "fadeIn" },
    { id: "#flip-x-animation", class: "flipInX" },
    { id: "#tada-animation", class: "tada" },
    { id: "#shake-animation", class: "shake" },
  ];

  animations.forEach(({ id, class: animClass }) => {
    $(id).on("click", function () {
      Swal.fire({
        title: `${animClass.replace(/([A-Z])/g, " $1")} Animation`,
        animation: false,
        customClass: `animated ${animClass}`,
        confirmButtonClass: "btn btn-primary",
        buttonsStyling: false,
      });
    });
  });

  // Tipos de alertas
  const types = [
    { id: "#type-success", type: "success", title: "Good job!" },
    { id: "#type-info", type: "info", title: "Info!" },
    { id: "#type-warning", type: "warning", title: "Warning!" },
    { id: "#type-error", type: "error", title: "Error!" },
  ];

  types.forEach(({ id, type, title }) => {
    $(id).on("click", function () {
      Swal.fire({
        title,
        text: "You clicked the button!",
        icon: type,
        confirmButtonClass: "btn btn-primary",
        buttonsStyling: false,
      });
    });
  });

  // Imagen personalizada
  $("#custom-icon").on("click", function () {
    Swal.fire({
      title: "Sweet!",
      text: "Modal with a custom image.",
      imageUrl: "../../../app-assets/images/slider/04.jpg",
      imageWidth: 400,
      imageHeight: 200,
      imageAlt: "Custom image",
      animation: false,
      confirmButtonClass: "btn btn-primary",
      buttonsStyling: false,
    });
  });

  // Auto cerrar
  $("#auto-close").on("click", function () {
    let timerInterval;
    Swal.fire({
      title: "Auto close alert!",
      html: "I will close in <strong></strong> seconds.",
      timer: 2000,
      confirmButtonClass: "btn btn-primary",
      buttonsStyling: false,
      didOpen: function () {
        Swal.showLoading();
        timerInterval = setInterval(function () {
          Swal.getHtmlContainer().querySelector("strong").textContent = Swal.getTimerLeft();
        }, 100);
      },
      willClose: function () {
        clearInterval(timerInterval);
      },
    }).then(function (result) {
      if (result.dismiss === Swal.DismissReason.timer) {
        console.log("I was closed by the timer");
      }
    });
  });

  // Cierre al hacer clic afuera
  $("#outside-click").on("click", function () {
    Swal.fire({
      title: "Click outside to close!",
      text: "This is a cool message!",
      allowOutsideClick: true,
      confirmButtonClass: "btn btn-primary",
      buttonsStyling: false,
    });
  });

  // Prompts en cadena
  $("#prompt-function").on("click", function () {
    Swal.mixin({
      input: "text",
      confirmButtonText: "Next →",
      showCancelButton: true,
      progressSteps: ["1", "2", "3"],
      confirmButtonClass: "btn btn-primary",
      cancelButtonClass: "btn btn-danger ml-1",
      buttonsStyling: false,
    })
      .queue([
        {
          title: "Question 1",
          text: "Chaining swal2 modals is easy",
        },
        "Question 2",
        "Question 3",
      ])
      .then(function (result) {
        if (result.value) {
          Swal.fire({
            title: "All done!",
            html: "Your answers: <pre><code>" + JSON.stringify(result.value) + "</code></pre>",
            confirmButtonText: "Lovely!",
          });
        }
      });
  });

  // Petición AJAX
  $("#ajax-request").on("click", function () {
    Swal.fire({
      title: "Search for a user",
      input: "text",
      inputAttributes: {
        autocapitalize: "off",
      },
      showCancelButton: true,
      confirmButtonText: "Look up",
      showLoaderOnConfirm: true,
      confirmButtonClass: "btn btn-primary",
      cancelButtonClass: "btn btn-danger ml-1",
      buttonsStyling: false,
      preConfirm: function (username) {
        return fetch("//api.github.com/users/" + username)
          .then((response) => {
            if (!response.ok) throw new Error(response.statusText);
            return response.json();
          })
          .catch((error) => {
            Swal.showValidationMessage("Request failed: " + error);
          });
      },
      allowOutsideClick: () => !Swal.isLoading(),
    }).then((result) => {
      if (result.value) {
        Swal.fire({
          title: `${result.value.login}'s avatar`,
          imageUrl: result.value.avatar_url,
        });
      }
    });
  });

  // Confirmaciones
  $(".confirm-text").on("click", function () {
    Swal.fire({
      title: "Are you sure?",
      text: "You won't be able to revert this!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Yes, delete it!",
      confirmButtonClass: "btn btn-primary",
      cancelButtonClass: "btn btn-danger ml-1",
      buttonsStyling: false,
    }).then(function (result) {
      if (result.value) {
        Swal.fire({
          icon: "success",
          title: "Deleted!",
          text: "Your file has been deleted.",
          confirmButtonClass: "btn btn-success",
        });
      }
    });
  });

  $("#confirm-color").on("click", function () {
    Swal.fire({
      title: "Are you sure?",
      text: "You won't be able to revert this!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Yes, delete it!",
      confirmButtonClass: "btn btn-primary",
      cancelButtonClass: "btn btn-danger ml-1",
      buttonsStyling: false,
    }).then(function (result) {
      if (result.value) {
        Swal.fire({
          icon: "success",
          title: "Deleted!",
          text: "Your file has been deleted.",
          confirmButtonClass: "btn btn-success",
        });
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        Swal.fire({
          title: "Cancelled",
          text: "Your imaginary file is safe :)",
          icon: "error",
          confirmButtonClass: "btn btn-success",
        });
      }
    });
  });
});
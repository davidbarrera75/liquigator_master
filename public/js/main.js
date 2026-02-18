$(function () {
    START.INIT();
    $("#body-client").length > 0 ? CLIENT.INIT() : null;
    $("#body-proyeccion").length > 0 ? PROYECCION.INIT() : null;
    $("#body-user").length > 0 ? USER.INIT() : null;
    $("#body-resume-mensual").length > 0 ? RESUME_MENSUAL.INIT() : null;
});


var START = {
    INIT: function () {
        $('.custom-file-input').on('change', function () {
            //get the file name
            var fileName = $(this).val();
            fileName = fileName.substring(fileName.length - 20, fileName.length);
            //replace the "Choose a file" label
            $(this).next('.custom-file-label').html('...' + fileName);
        })
    }
}

var PROYECCION = {
    INIT: function () {
        this.EVENTS();
    },
    EVENTS: function () {
        $('.proyeccion-eliminar').click(function (e) {
            PROYECCION.MODELS.proyection_delete($(this).data('url'));
        });
    },
    MODELS: {
        proyection_delete: function (url) {
            if (confirm('¿Está seguro de eliminar esta proyección?')) {
                window.location.replace(url);
            } else {
                alert('Se ha cancelado');
            }
        }
    }
}

var CLIENT = {
    INIT: function () {
        this.EVENTS();
    },
    EVENTS: function () {
        CLIENT.MODELS.daterange($('.daterange'));
        CLIENT.MODELS.tinymce('textarea#conclusiones');
        $("#save-comment").click(function (e) {
            e.preventDefault();
            CLIENT.MODELS.save_comment($(this));
        })


    },
    MODELS: {
        daterange: function ($method) {
            $method.daterangepicker({
                "showDropdowns": true,
                // "minYear": 2021,
                "showWeekNumbers": true,
                "locale": {
                    "format": "MM/YYYY",
                    "separator": " - ",
                    "applyLabel": "Aplicar",
                    "cancelLabel": "Cancelar",
                    "fromLabel": "Desde",
                    "toLabel": "Hasta",
                    "customRangeLabel": "Custom",
                    "weekLabel": "S",
                    "daysOfWeek": [
                        "Do",
                        "Lu",
                        "Ma",
                        "Mi",
                        "Ju",
                        "Vi",
                        "Sa"
                    ],
                    "monthNames": [
                        "Enero",
                        "Febrero",
                        "Marzo",
                        "Abril",
                        "Mayo",
                        "Junio",
                        "Julio",
                        "Agosto",
                        "Septiembre",
                        "Octubre",
                        "Noviembre",
                        "Deciembre"
                    ],
                    "firstDay": 1
                },
                "alwaysShowCalendars": true
            }, function (start, end, label) {
                console.log('New date range selected: ' + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD') + ' (predefined range: ' + label + ')');
            });
        },
        tinymce: function (textarea) {
            tinymce.init({
                selector: textarea,
                height: 300,
                menubar: false,
                plugins: [
                    'advlist autolink lists link image charmap print preview anchor',
                    'searchreplace visualblocks code fullscreen',
                    'insertdatetime media table paste code help wordcount'
                ],
                toolbar: 'undo redo | formatselect | ' +
                    'bold italic backcolor | alignleft aligncenter ' +
                    'alignright alignjustify | bullist numlist outdent indent | ' +
                    'removeformat | help',
                content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
            });
        },
        save_comment: function ($this) {
            var send_to = $this.data('url');
            var value_send = tinymce.get("conclusiones").getContent();
            var msg = $("#comment-save-success");
            $.post(send_to, {comentario: value_send}, function (data) {
                msg.text(data.message)
            });
            return false;
        }


    }
};

var USER = {
    INIT: function () {
        this.EVENTS();
    },
    EVENTS: function () {
        $(".eliminar-cliente").click(function () {
            USER.MODELS.eliminar_cliente($(this));
            return false;
        });
        $(".options").change(function () {
            USER.MODELS.cambio_carga($(this).val());
        })
    },
    MODELS: {
        eliminar_cliente($this) {
            var id = $this.data('url');
            if (confirm('Está seguro de eliminar este informe?')) {
                $.ajax({
                    url: id,
                    type: 'DELETE',
                    dataType: 'application/json',
                    complete: function (xhr, status) {
                        location.reload();
                    }
                });
            } else {
                return false;
            }
        },
        cambio_carga(val) {
            var $pdf = $(".carga-pdf");
            var $excel = $(".carga-excel");
            switch (val) {
                case 'pdf':
                    $pdf.show('slow');
                    $pdf.find('input').attr('required', true).attr('name', 'file');
                    $excel.hide();
                    $excel.find('input').removeAttr('required').removeAttr('name');
                    break;
                case 'excel':
                    $excel.show('slow');
                    $excel.find('input').attr('required', true).attr('name', 'file');
                    $pdf.hide();
                    $pdf.find('input').removeAttr('required').removeAttr('name');
                    break;

            }
        }
    }
}

var RESUME_MENSUAL = {
    INIT: function () {
        console.log('RESUME_MENSUAL iniciado');
        this.EVENTS();
    },
    EVENTS: function () {
        // Usar delegación de eventos apropiada
        $(document).on('click', '.th-year', function (e) {
            e.preventDefault();
            console.log('Click detectado en año');
            RESUME_MENSUAL.MODELS.show_year($(this));
        });

        // Inicializar editables después de que el DOM esté listo
        setTimeout(function() {
            RESUME_MENSUAL.MODELS.dias_reporte_mensual();
        }, 100);
    },
    MODELS: {
        show_year: function($this) {
            var year = $this.data('target');
            console.log('Mostrando año: ' + year);
            var $table = $(year);
            console.log('Filas encontradas: ' + $table.length);

            $(".th-year").removeClass('active');
            if ($table.is(':visible')) {
                $table.hide();
            } else {
                $(".tr").hide();
                $table.show();
                $this.addClass('active');
            }
        },
        dias_reporte_mensual: function() {
            console.log('Inicializando editables');
            //Array of days 0 to 30
            var days = [];
            for (var i = 0; i <= 30; i++) {
                days.push({value: i, text: i});
            }

            $(".dias-reporte-mensual").editable({
                type: 'select',
                source: days,
                inputclass: 'form-control-sm',
                url: function(params) {
                    var d = new $.Deferred();
                    var url = $(this).data('url');
                    var pk = $(this).data('pk');
                    var $editableElement = $(this);
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {pk: pk, value: params.value},
                        success: function(response) {
                            console.log('Días actualizado', response);
                            // Actualizar la celda de valor en la misma fila
                            if (response.nuevoValor !== undefined) {
                                var $row = $editableElement.closest('tr');
                                var $valorCell = $row.find('.valor-reporte-mensual');
                                $valorCell.text(response.nuevoValor);
                                $valorCell.data('value', response.nuevoValor);
                            }
                            d.resolve(response);
                        },
                        error: function(xhr, status, error) {
                            console.error('Error actualizando días', error);
                            d.reject('Error al actualizar');
                        }
                    });
                    return d.promise();
                },
                success: function(response, newValue) {
                    console.log('Días actualizado correctamente');
                }
            });

            $(".valor-reporte-mensual").editable({
                type: 'number',
                inputclass: 'form-control-sm',
                url: function(params) {
                    var d = new $.Deferred();
                    var url = $(this).data('url');
                    var pk = $(this).data('pk');
                    $.ajax({
                        url: url,
                        type: 'POST',
                        data: {pk: pk, value: params.value},
                        success: function(response) {
                            console.log('Valor actualizado', response);
                            d.resolve(response);
                        },
                        error: function(xhr, status, error) {
                            console.error('Error actualizando valor', error);
                            d.reject('Error al actualizar');
                        }
                    });
                    return d.promise();
                },
                success: function(response, newValue) {
                    console.log('Valor actualizado correctamente');
                }
            });

        }
    }
}

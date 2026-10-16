var panelProductoId = null;
var panelSeleccion = {};
var panelVariante = null;
var catalogoEstado = 'cargando';

function AbrirSelectorProducto() {
    RenderProductosPanel();
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('selectorProducto')).show();
}
function VariantesPanel() {
    return productosOrden.map(function(id) { return productos[id]; }).filter(function(v) { return String(v.pro_id) === String(panelProductoId); });
}
function RenderProductosPanel() {
    var q = ($('#buscar_producto_panel').val() || '').toLocaleLowerCase().trim();
    var list = $('#panel_productos');
    var scrollTop = list.scrollTop();
    var groups = {};
    productosOrden.forEach(function(id) {
        var v = productos[id];
        if (!groups[v.pro_id]) groups[v.pro_id] = { nombre: v.producto_nombre, categoria: v.cat_nombre, variantes: [] };
        groups[v.pro_id].variantes.push(v);
    });
    list.empty();
    Object.keys(groups).forEach(function(id) {
        var g = groups[id];
        if (q && !g.variantes.some(function(v) { return [v.pro_nombre, v.pva_sku, v.pva_codigo_barras].join(' ').toLocaleLowerCase().includes(q); })) return;
        var b = $('<button type="button">').addClass('venta-producto-card').toggleClass('seleccionado', String(id) === String(panelProductoId));
        b.append($('<strong>').text(g.nombre), $('<small>').text((g.categoria || 'Sin categoría') + ' · ' + g.variantes.length + ' variantes'));
        b.on('click', function() {
            panelProductoId = id; panelSeleccion = {}; panelVariante = null;
            RenderProductosPanel(); RenderAtributosPanel();
        });
        list.append(b);
    });
    if (!list.children().length) {
        var mensaje=catalogoEstado==='error'?'No se pudo cargar el catálogo.':catalogoEstado==='cargando'?'Cargando catálogo…':productosOrden.length?'No se encontraron productos.':'No hay productos habilitados en el catálogo.';
        list.append($('<p class="text-muted p-3">').text(mensaje));
        if(catalogoEstado==='error')list.append($('<button type="button" class="btn btn-outline-primary">').text('Reintentar').on('click',CargarProductos));
    }
    list.scrollTop(scrollTop);
}
function RenderAtributosPanel() {
    var variants = VariantesPanel(), groups = {};
    if (!variants.length) return;
    $('#panel_producto_nombre').text(variants[0].producto_nombre);
    variants.forEach(function(v) {
        v.atributos.forEach(function(a) {
            if (!groups[a.atr_id]) groups[a.atr_id] = { nombre: a.atr_nombre, valores: {} };
            groups[a.atr_id].valores[a.ava_id] = a;
        });
    });
    var host = $('#panel_atributos').empty();
    Object.keys(groups).forEach(function(id) {
        var g = groups[id], section = $('<div class="venta-atributo-grupo">');
        section.append($('<h6>').text(g.nombre));
        var choices = $('<div class="venta-atributo-valores">');
        Object.keys(g.valores).forEach(function(value) {
            var a = g.valores[value], selected = String(panelSeleccion[id]) === String(value);
            var enabled = variants.some(function(v) {
                return v.atributos.some(function(x) { return String(x.ava_id) === String(value); }) &&
                    Object.keys(panelSeleccion).every(function(other) { return other === id || v.atributos.some(function(x) { return String(x.atr_id) === other && String(x.ava_id) === String(panelSeleccion[other]); }); });
            });
            var b = $('<button type="button">').addClass('venta-atributo-chip').toggleClass('seleccionado', selected).prop('disabled', !enabled).attr('aria-pressed', selected);
            if (a.ava_color && /^#[0-9a-f]{6}$/i.test(a.ava_color)) b.append($('<span class="venta-color-dot">').css('background-color', a.ava_color));
            b.append($('<span>').text(a.ava_nombre), $('<i class="fa fa-check">'));
            b.on('click', function() { if (selected) delete panelSeleccion[id]; else panelSeleccion[id] = value; RenderAtributosPanel(); });
            choices.append(b);
        });
        host.append(section.append(choices));
    });
    if (Object.keys(groups).length) {
        host.append($('<button type="button" class="btn btn-link p-0 mt-2">').text('Limpiar selección').on('click', function() { panelSeleccion = {}; RenderAtributosPanel(); }));
    }
    var matches = variants.filter(function(v) { return Object.keys(groups).every(function(id) { return panelSeleccion[id] && v.atributos.some(function(a) { return String(a.atr_id) === id && String(a.ava_id) === String(panelSeleccion[id]); }); }); });
    panelVariante = matches.length === 1 ? matches[0] : null;
    var info = $('#panel_variante_info').empty();
    if (panelVariante) {
        var v = panelVariante;
        info.append($('<strong>').text(v.pro_nombre), $('<span>').text('SKU: ' + v.pva_sku), $('<span>').text('Unidad: ' + v.und_abrev + ' · Precio: S/ ' + Number(v.pro_prec_venta).toFixed(2)));
        if (!window.cotizacionContexto) info.append($('<span>').text('Stock: ' + Number(v.pro_stock).toFixed(3) + ' ' + v.und_abrev));
        if (!window.cotizacionContexto && Number(v.pro_stock) <= 0) info.append($('<small>').text(window.ventaSinStock ? 'Venta sin stock habilitada.' : 'Sin stock disponible en esta tienda.'));
    } else info.text('Selecciona un valor de cada atributo para ver el precio, la unidad y el stock.');
    $('#panel_usar_variante').prop('disabled', !panelVariante);
}
function UsarVarianteSeleccionada() {
    if (!panelVariante) return;
    $('#ven_ins_id').val(panelVariante.pva_id).trigger('change');
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('selectorProducto')).hide();
}
function ActualizarSaldoVenta() {
    var total = Math.round(detalle.reduce(function(sum, row) { return sum + row.subtotal; }, 0) * 100) / 100;
    var raw = $('#ven_monto_pago').val(), amount = Number(raw), valid = raw !== '' && Number.isFinite(amount) && amount >= 0 && amount <= total && Math.abs(amount - Math.round(amount*100)/100) < 0.000001;
    $('#pago_ahora').text('S/ ' + (valid ? amount : 0).toFixed(2));
    $('#pago_saldo').text('S/ ' + Math.max(0, total - (valid ? amount : 0)).toFixed(2));
    $('#pago_estado').text(!detalle.length ? 'Agrega productos para calcular el saldo.' : !valid ? 'Ingresa un monto válido entre 0 y el total.' : Math.round(amount*100) === Math.round(total*100) ? 'Pago completo · comprobante disponible para emitir.' : 'Pago pendiente · emisión bloqueada hasta completar el pago.').toggleClass('text-success', valid && amount === total).toggleClass('text-warning', valid && amount < total);
}
function ActualizarTotalItem() {
    var cantidad = Number($('#ven_cantidad').val()), precio = Number($('#ven_precio_unit').val());
    var total = Number.isFinite(cantidad) && Number.isFinite(precio) && cantidad > 0 && precio >= 0 ? cantidad * precio : 0;
    $('#ven_total_item').val('S/ ' + total.toFixed(2));
}
$(function() {
    $('#buscar_producto_panel').on('input', RenderProductosPanel);
    $('#ven_cantidad, #ven_precio_unit').on('input change', ActualizarTotalItem);
    $('#ven_ins_id').on('change.totalItem', function() { ActualizarTotalItem(); });
});

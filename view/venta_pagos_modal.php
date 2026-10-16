<script>window.ventaPagoCsrf=<?= json_encode($_SESSION['venta_pago_csrf']??'') ?>;</script>
<div class="modal fade" id="modalPagosVenta" tabindex="-1" aria-labelledby="pagosTitulo" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content pagos-modal">
  <div class="modal-header"><div class="pagos-heading"><span class="pagos-icon"><i class="fas fa-wallet" aria-hidden="true"></i></span><div><small>INFORMACIÓN ECONÓMICA</small><h5 id="pagosTitulo">Pagos de la venta</h5></div></div><button type="button" class="pagos-close" data-bs-dismiss="modal" aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button></div>
  <div class="modal-body"><div id="pagosMensaje" role="status"></div><div id="pagosContenido" hidden>
   <div class="pagos-cifras"><div><small>Total de la venta</small><strong id="pagosTotal"></strong></div><div><small>Pagado hasta ahora</small><strong id="pagosPagado"></strong></div><div class="pagos-deuda"><small>Saldo pendiente</small><strong id="pagosSaldo"></strong></div></div>
   <div class="pagos-columns"><section class="pagos-history"><h6><i class="fas fa-history" aria-hidden="true"></i> Historial de pagos</h6><p class="pagos-caption">Abonos registrados en esta venta.</p><div id="pagosHistorial"></div></section>
   <section class="pagos-entry"><form id="formNuevoPago" novalidate><h6>Registrar un abono</h6><p class="pagos-caption">Completa los datos del nuevo pago.</p><div class="row g-3">
    <div class="col-12"><label for="pagoMetodo" class="form-label">Método de pago</label><select id="pagoMetodo" class="form-select" required></select></div>
    <div class="col-12"><label for="pagoCuenta" class="form-label">Cuenta de destino</label><select id="pagoCuenta" class="form-select" required></select></div>
    <div class="col-sm-6"><label for="pagoMonto" class="form-label">Monto del abono (S/)</label><input id="pagoMonto" type="number" class="form-control" min="0.01" step="0.01" placeholder="0.00" required></div>
    <div class="col-sm-6"><label for="pagoOperacion" class="form-label">N° de operación <small>(opcional)</small></label><input id="pagoOperacion" class="form-control" maxlength="100" placeholder="Ej. 001234"></div>
   </div><div class="pagos-projection"><span>Saldo después del abono</span><strong id="pagoSaldoProyectado" aria-live="polite"></strong></div><button id="btnRegistrarPago" type="submit" class="btn btn-primary">Registrar pago</button></form>
   <div id="pagosCompletos" hidden></div><button id="pagosEmitir" class="btn btn-success mt-3" type="button" hidden>Emitir comprobante</button></section></div>
  </div></div>
 </div></div>
</div>

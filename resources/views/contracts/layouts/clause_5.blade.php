<p class="break justify bold">5. Depósito en garantía:</p>
@if($contract->office->type->name == 'Física' || $contract->office->type->name == 'Virtual')
	<ul class="b-up-alpha less-li-he justify">
		<li class="one-line-sp">“EL CLIENTE” entrega en este acto la suma de ${{$contract->office->price * 0.90}} ({{$contract->monthly_payment_str}}) más IVA al valor agregado cantidad que “EL PRESTADOR” conservará en depósito hasta la terminación del presente contrato y queda autorizado para aplicar dicha cantidad al pago de saldos insolutos que “EL CLIENTE” pudiera adeudar. en caso de que “EL CLIENTE” no adeude cantidad alguna, la suma depositada en garantía le será devuelta sin necesidad de ningún trámite adicional (será indispensable para la devolución, entregar baja de domicilio ante el SAT en caso de estar registrado como domicilio fiscal y comprobantes de no adeudo de servicios contratados por su cuenta, tales como líneas de teléfono, etc.), en un plazo máximo de 30 días contados a partir de la fecha de vencimiento del contrato, siempre y cuando cumpla la vigencia del mismo. en caso de no presentar los documentos mencionados, el depósito quedará a disposición de cobro en favor de “EL PRESTADOR”.</li>
	</ul>
@else
	<ul class="b-up-alpha less-li-he justify">
		<li class="one-line-sp">“EL CLIENTE” entrega en este acto la suma de ${{$contract->office->price * 0.90}} ({{$contract->monthly_payment_str}}), cantidad que “EL PRESTADOR” conservará en depósito hasta la terminación del presente contrato y queda autorizado para aplicar dicha cantidad al pago de saldos insolutos que “EL CLIENTE” pudiera adeudar. En caso de que “EL CLIENTE” no adeude cantidad alguna, la suma depositada en garantía le será devuelta sin necesidad de ningún trámite adicional.</li>
	</ul>
@endif
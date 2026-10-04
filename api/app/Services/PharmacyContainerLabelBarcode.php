<?php
namespace App\Services;
use Illuminate\Support\Str;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

/** Internal proof identity only; not a drug/package identifier or release authority. */
class PharmacyContainerLabelBarcode
{
 public function create():string{return 'FMTCL-'.strtoupper(Str::random(20));}
 public function valid(string $code):bool{return preg_match('/^FMTCL-[A-Z0-9]{20}$/D',$code)===1;}
 public function svg(string $code):string
 {
  abort_unless($this->valid($code),422,'Unsupported internal container label code.');
  $barcode=(new TypeCode128())->getBarcode($code);$renderer=new SvgRenderer();$renderer->setSvgType(SvgRenderer::TYPE_SVG_INLINE);
  return $renderer->render($barcode,$barcode->getWidth(),50);
 }
}

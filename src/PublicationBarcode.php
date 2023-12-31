<?php

namespace SQKhor\Barcode;

class PublicationBarcode {
	// encoded bar data in 0 and 1
	private string $bars_left = "";
	private string $bars_right = "";
	private string $bars_addon = "";

	// original codes
	private string $code = "";
	private ?string $issn = null;
	private ?string $addon = null;

	// settings
	public int $bar_width = 4;
	public int $bar_height = 200;
	public int $jpeg_quality = 90;

	// code type
	private string $type = 'isbn';

	public function __construct (string $code, ?string $addon = null) {
		$this->generate($code, $addon);
	}

	/**
	 * Do all necessary calculations for the barcode.
	 * 
	 * @param string      $code    Either 13-digit ISBN or ISSN barcode, or 8-digit ISSN number
	 * @param string|null $addon   Add-on barcode on the right side, could be price indicator for ISBN or issue number for ISSN
	 */
	public function generate (string $code, ?string $addon = null) {
		// reset
		$this->bars_left = "";
		$this->bars_right = "";
		$this->bars_addon = "";
		$this->code = "";
		$this->issn = null;
		$this->addon = null;

		// normalise
		$code = strtoupper($code);
		$code = preg_replace("/\D/", "", $code);

		// detect type and figure out ISSN
		switch (strlen($code)) {
			case 7:
			case 8:
				$this->type = "issn";
				$issn = substr($code, 0, 7);
				$code = "977" . $issn . "00";
				$code .= $this->get_check_digit($code);
				break;
			case 13:
				$identifier = substr($code, 0, 3);
				if ($identifier == '978') $this->type = 'isbn';
				elseif ($identifier == '977') $this->type = 'issn';
				else $this->type = 'ean';

				if ($this->type == 'issn') {
					$issn = substr($code, 3, 7);
				}
				break;
		}

		// append ISSN with check digit
		if ($this->type == 'issn') {
			$issn_check_digit = $this->get_issn_check_digit($issn);
			$this->issn = $issn . $issn_check_digit;
		}

		// encode barcodes
		$this->encode_ean_13($code);
		if (!empty($addon)) {
			if ($this->type != 'issn' || strlen($addon) > 2) {
				$this->encode_ean_5($addon);
			} else {
				$this->encode_ean_2($addon);
			}
		}
	}

	/**
	 * Returns image data of the generated barcode.
	 * 
	 * @param string      $format  Currently only 'svg' is supported
	 */
	public function render (string $format) {
		// output
		switch ($format) {
			case 'png':
				return $this->png();
			case 'jpg':
			case 'jpeg':
				return $this->jpeg();
			case 'svg':
			default:
				return $this->svg();
		}
	}

	/**
	 * Calculate check-digit of 8-digit ISSN number.
	 * 
	 * @param string $code  The first 7 digit of ISSN number
	 */
	private function get_issn_check_digit ($code):string {
		$sum = 0;
		for ($i = 0; $i < 7; $i++) {
			$digit = $code[$i];
			$multiplier = 8 - $i;
			$sum += $digit * $multiplier;
		}
		$modulo = $sum % 11;
		if ($modulo == 0) return 0;
		$modulo = 11 - $modulo;
		return $modulo === 10 ? 'X' : $modulo;
	}

	/**
	 * Prepare to encode EAN-13. Code will be splitted to 3 parts: first_digit, left_digits and right_digits.  
	 * This method will check the check-digit, determine the parity of the left part and send to encode.
	 * 
	 * @param string $code  The 13-digit barcode
	 */
	private function encode_ean_13 (string $code) {
		$check_digit = $this->get_check_digit($code);
		if ($check_digit != $code[12]) {
			trigger_error("Check digit should be $check_digit instead of {$code[12]}");
		}
		$this->code = substr($code, 0, 12) . $check_digit;

		$first_digit = substr($code, 0, 1);
		$left_digits = substr($code, 1, 6);
		$right_digits = substr($code, 7, 5) . $check_digit;

		$this->encode('left', $left_digits, $this->parity_13[$first_digit]);
		$this->encode('right', $right_digits);
	}

	/**
	 * Calculate the check-digit of EAN-13.
	 * 
	 * @param string $code  The 13-digit barcode
	 */
	private function get_check_digit ($code):int {
		$odd = $even = 0;
		for ($i = 0; $i < 12; $i++) {
			if (($i + 1) % 2 === 0) {
				$even += intval($code[$i]);
			} else {
				$odd += intval($code[$i]);
			}
		}
		$sum = $odd + ($even * 3);
		$check_digit = 0;
		if ($sum % 10 !== 0) {
			$check_digit = 10 - ($sum % 10);
		}
		return $check_digit;
	}

	/**
	 * Prepare to encode EAN-5.  
	 * This method will determine the parity and send to encode.
	 * 
	 * @param string $code  The 5-digit add-on code
	 */
	private function encode_ean_5 (string $code) {
		$code = sprintf("%05d", $code);
		$this->addon = $code;
		$odd = $even = 0;
		for ($i = 0; $i < 5; $i++) {
			if (($i + 1) % 2 === 0) {
				$even += intval($code[$i]);
			} else {
				$odd += intval($code[$i]);
			}
		}
		$sum = ($odd * 3) + ($even * 9);
		$checksum = $sum % 10;
		$parity = $this->parity_5[$checksum];
		$this->encode('addon', $code, $parity);
	}

	/**
	 * Prepare to encode EAN-2.  
	 * This method will determine the parity and send to encode.
	 * 
	 * @param string $code  The 2-digit add-on code
	 */
	private function encode_ean_2 (string $code) {
		$code = sprintf("%02d", $code);
		$this->addon = $code;
		$modulo_4 = intval($code) % 4;
		$parity = $this->parity_2[$modulo_4];
		$this->encode('addon', $code, $parity);
	}

	/**
	 * Encode the digits into bars.
	 * 
	 * @param string      $part    Which part to encode? Accepts left|right|addon
	 * @param string      $code    The code for that part
	 * @param string|null $parity  Which parity to be used, not required for 'right' part
	 */
	private function encode (string $part, string $code, ?string $parity = null) {
		switch ($part) {
			case 'left': {
				for ($i = 0; $i < 6; $i++) {
					$digit = $code[$i];
					$bars = ($parity[$i] == 'L') ? $this->code_left[$digit] : strrev($this->code_right[$digit]);
					$this->bars_left .= $bars;
				}
				break;
			}
			case 'right': {
				for ($i = 0; $i < 6; $i++) {
					$bars = $this->code_right[$code[$i]];
					$this->bars_right .= $bars;
				}
				break;
			}
			case 'addon': {
				$bars = [];
				for ($i = 0; $i < strlen($parity); $i++) {
					$digit = $code[$i];
					$bars[] = ($parity[$i] == 'L') ? $this->code_left[$digit] : strrev($this->code_right[$digit]);
				}
				$this->bars_addon = "1011" . implode("01", $bars);
				break;
			}
		}
	}

	/**
	 * Generate SVG data
	 */
	public function svg () {
		extract($this->measure());

		$label = empty($this->issn) ? '' : "ISSN " . substr($this->issn, 0, 4) . "-" . substr($this->issn, 4, 4);
		$ean_13 = ["101", $this->bars_left, "01010", $this->bars_right, "101"];

		ob_start();
		?>
<svg width="<?= $img_width ?>" height="<?= $img_height ?>" viewBox="0 0 <?= $img_width ?> <?= $img_height ?>" xmlns="http://www.w3.org/2000/svg">
	<style>
		.code {
			font-family: Arial;
			font-size: 28px;
		}

		.label {
			font-family: Arial;
			font-size: 48px;
			text-anchor: middle;
			text-align: center;
		}
	</style>

	<?php if (!empty($this->issn)): ?>
		<g>
			<text class="label" x="<?= ($img_width - $digit_width) / 2 + $digit_width ?>" y="48"><?= $label ?></text>
		</g>
	<?php endif ?>

	<g>
		<!-- EAN-13 Bars -->
		<?php
			$x = $digit_width;
			for ($part = 0; $part < 5; $part++) {
				$height = ($part % 2 === 0) ? $bar_height : $bar_height - 20;
				$bar_thickness = 0;
				for ($i = 0; $i < strlen($ean_13[$part]); $i++) {
					$bar = $ean_13[$part][$i];
					if ($bar) {
						$bar_thickness += $bar_width;
						if ($i + 1 == strlen($ean_13[$part]) || $ean_13[$part][$i+1] == '0') {
							echo "<rect x=\"$x\" y=\"$label_height\" width=\"$bar_thickness\" height=\"$height\" />";
							$x += $bar_thickness;
							$bar_thickness = 0;
						}
					} else {
						$x += $bar_width;
					}
				}
			}
		?>
	</g>
	<g>
		<!-- EAN-13 Text -->
		<text class="code" x="0" y="<?= $label_height + $bar_height + 10 ?>" textLength="<?= $digit_width ?>"><?= substr($this->code, 0, 1) ?></text>
		<text class="code" x="<?= 13.5 * $bar_width ?>" y="<?= $label_height + $bar_height + 10 ?>" textLength="<?= 35 * $bar_width ?>"><?= substr($this->code, 1, 6) ?></text>
		<text class="code" x="<?= 60.5 * $bar_width ?>" y="<?= $label_height + $bar_height + 10 ?>" textLength="<?= 35 * $bar_width ?>"><?= substr($this->code, 7, 6) ?></text>
	</g>

	<?php if (!empty($this->addon)): ?>
		<g>
			<!-- Add-On Text -->
			<text class="code" x="<?= $img_width - $addon_width + ($digit_width / 2) ?>" y="<?= $label_height + 20 ?>" textLength="<?= $addon_width - $digit_width ?>"><?= $this->addon ?></text>
		</g>
		<g>
			<!-- Add-On Bars -->
			<?php
				$x = $ean_13_width + $gap_width;
				$y = $label_height + 30;
				$height = $bar_height - 30;
				$bar_thickness = 0;
				for ($i = 0; $i < strlen($this->bars_addon); $i++) {
					$bar = $this->bars_addon[$i];
					if ($bar) {
						$bar_thickness += $bar_width;
						if ($i + 1 == strlen($this->bars_addon) || $this->bars_addon[$i+1] == '0') {
							echo "<rect x=\"$x\" y=\"$y\" width=\"$bar_thickness\" height=\"$height\" />";
							$x += $bar_thickness;
							$bar_thickness = 0;
						}
					} else {
						$x += $bar_width;
					}
				}
			?>
		</g>
	<?php endif ?>
</svg>
		<?php
		$svg = ob_get_clean();
		return $svg;
	}

	/**
	 * Generate PNG data
	 */
	public function png () {
		$image_data = $this->render_raster(true);
		ob_start();
		imagepng($image_data);
		imagedestroy($image_data);
		return ob_get_clean();
	}

	/**
	 * Generate JPEG data
	 */
	public function jpeg () {
		$image_data = $this->render_raster(false);
		ob_start();
		imagejpeg($image_data, null, $this->jpeg_quality);
		imagedestroy($image_data);
		return ob_get_clean();
	}

	private function measure () {
		$bar_width = $this->bar_width;
		$bar_height = $this->bar_height;
		$ean_13_width = 102 * $bar_width;
		$ean_5_width = 47 * $bar_width;
		$ean_2_width = 20 * $bar_width;
		$digit_width = 7 * $bar_width;

		$gap_width = 0;
		$addon_width = 0;
		if (!empty($this->addon)) {
			$gap_width = 7 * $bar_width;
			$addon_width = ($this->type != 'issn' || strlen($this->addon) > 2) ? $ean_5_width : $ean_2_width;
		}
		$img_width = $ean_13_width + $gap_width + $addon_width;

		$label_height = empty($this->issn) ? 0 : 80;
		$img_height = $label_height + $bar_height + 20;

		return compact('bar_width', 'bar_height', 'ean_13_width', 'ean_5_width', 'ean_2_width', 'digit_width', 'gap_width', 'addon_width', 'img_width', 'img_height', 'label_height');
	}

	private function render_raster (bool $alpha = true) {
		extract($this->measure());

		$label = empty($this->issn) ? '' : "ISSN " . substr($this->issn, 0, 4) . "-" . substr($this->issn, 4, 4);
		$font = __DIR__ . "/font/Arial.ttf";

		if ($alpha) {
			$image = imagecreatetruecolor($img_width, $img_height);
			imagealphablending($image, true);
			$transparency = imagecolorallocatealpha($image, 0, 0, 0, 127);
			imagefill($image, 0, 0, $transparency);
			imagesavealpha($image, true);
		} else {
			$image = imagecreate($img_width, $img_height);
			imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
		}

		$black = imagecolorallocate($image, 0, 0, 0);

		// ISSN Label
		if (!empty($this->issn)) {
			$text_measurements = imagettfbbox(35, 0, $font, $label);
			$text_width = $text_measurements[4] - $text_measurements[6];
			$x = ($img_width - $digit_width - $text_width) / 2 + $digit_width;
			$y = 48;
			imagettftext($image, 35, 0, round($x), round($y), $black, $font, $label);
		}

		// EAN-13 Bars
		$ean_13 = ["101", $this->bars_left, "01010", $this->bars_right, "101"];
		$x = $digit_width;
		for ($part = 0; $part < 5; $part++) {
			$height = ($part % 2 === 0) ? $bar_height : $bar_height - 20;
			$bar_thickness = 0;
			for ($i = 0; $i < strlen($ean_13[$part]); $i++) {
				$bar = $ean_13[$part][$i];
				if ($bar) {
					$bar_thickness += $bar_width;
					if ($i + 1 == strlen($ean_13[$part]) || $ean_13[$part][$i+1] == '0') {
						imagefilledrectangle($image, $x, $label_height, $x + $bar_thickness, $label_height + $height, $black);
						$x += $bar_thickness;
						$bar_thickness = 0;
					}
				} else {
					$x += $bar_width;
				}
			}
		}

		// EAN-13 Text
		$y = $label_height + $bar_height + 10;
		imagettftext($image, 20, 0, 0, round($y), $black, $font, $this->code[0]);

		$textbox_width = 29 * $bar_width;
		$text_measurements = imagettfbbox(20, 0, $font, substr($this->code, 1, 6));
		$text_width = $text_measurements[4] - $text_measurements[6];
		$text_gap = ($textbox_width - $text_width) / 5;
		for ($i = 1; $i <= 6; $i++) {
			$x = 13.5 * $bar_width;
			$x += ($i - 1) * $textbox_width / 6; // character width
			$x += ($i - 1) * $text_gap; // character gaps
			imagettftext($image, 20, 0, round($x), round($y), $black, $font, $this->code[$i]);
		}

		$text_measurements = imagettfbbox(20, 0, $font, substr($this->code, 7, 6));
		$text_width = $text_measurements[4] - $text_measurements[6];
		$text_gap = ($textbox_width - $text_width) / 5;
		for ($i = 7; $i <= 12; $i++) {
			$x = 60 * $bar_width;
			$x += ($i - 7) * $textbox_width / 6; // character width
			$x += ($i - 7) * $text_gap; // character gaps
			imagettftext($image, 20, 0, round($x), round($y), $black, $font, $this->code[$i]);
		}

		if (!empty($this->addon)) {
			// Add-On Text
			$addon_length = strlen($this->addon);
			$textbox_width = $addon_width - $digit_width;
			$text_measurements = imagettfbbox(20, 0, $font, $this->addon);
			$text_width = $text_measurements[4] - $text_measurements[6];
			$text_gap = ($textbox_width - $text_width) / ($addon_length - 1);
			for ($i = 0; $i < $addon_length; $i++) {
				$x = $img_width - $addon_width + ($digit_width / 2);
				$x += $i * $text_width / $addon_length; // character width
				$x += $i * $text_gap; // character gaps
				$y = $label_height + 20;
				imagettftext($image, 20, 0, $x, $y, $black, $font, $this->addon[$i]);
			}
	
			// Add-On Bars
			$x = $ean_13_width + $gap_width;
			$y = $label_height + 30;
			$height = $bar_height - 30;
			$bar_thickness = 0;
			for ($i = 0; $i < strlen($this->bars_addon); $i++) {
				$bar = $this->bars_addon[$i];
				if ($bar) {
					$bar_thickness += $bar_width;
					if ($i + 1 == strlen($this->bars_addon) || $this->bars_addon[$i+1] == '0') {
						imagefilledrectangle($image, $x, $y, $x + $bar_thickness, $y + $height, $black);
						$x += $bar_thickness;
						$bar_thickness = 0;
					}
				} else {
					$x += $bar_width;
				}
			}
		}

		return $image;
	}

	/**
	 * Bars for L-digits.
	 */
	private array $code_left = [
		0 => '0001101',
		1 => '0011001',
		2 => '0010011',
		3 => '0111101',
		4 => '0100011',
		5 => '0110001',
		6 => '0101111',
		7 => '0111011',
		8 => '0110111',
		9 => '0001011'
	];

	/**
	 * Bars for R-digits. This is to avoid repeated calculation from L-digits.
	 */
	private array $code_right = [
		0 => '1110010',
		1 => '1100110',
		2 => '1101100',
		3 => '1000010',
		4 => '1011100',
		5 => '1001110',
		6 => '1010000',
		7 => '1000100',
		8 => '1001000',
		9 => '1110100'
	];

	/**
	 * Parity for G-digits.
	 */
	private array $parity_13 = [
		0 => 'LLLLLL',
		1 => 'LLGLGG',
		2 => 'LLGGLG',
		3 => 'LLGGGL',
		4 => 'LGLLGG',
		5 => 'LGGLLG',
		6 => 'LGGGLL',
		7 => 'LGLGLG',
		8 => 'LGLGGL',
		9 => 'LGGLGL'
	];

	/**
	 * Parity for EAN-5.
	 */
	private array $parity_5 = [
		0 => 'GGLLL',
		1 => 'GLGLL',
		2 => 'GLLGL',
		3 => 'GLLLG',
		4 => 'LGGLL',
		5 => 'LLGGL',
		6 => 'LLLGG',
		7 => 'LGLGL',
		8 => 'LGLLG',
		9 => 'LLGLG'
	];

	/**
	 * Parity for EAN-2.
	 */
	private array $parity_2 = [
		0 => 'LL',
		1 => 'LG',
		2 => 'GL',
		3 => 'GG'
	];
}
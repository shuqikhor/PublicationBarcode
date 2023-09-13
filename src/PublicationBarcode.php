<?php

namespace sqkhor\Barcode;

class PublicationBarcode {
	private string $bars_left = "";
	private string $bars_right = "";
	private string $bars_addon = "";
	private string $code = "";
	private string $type = 'isbn';
	private ?string $issn = null;
	private ?string $addon = null;

	public function render (string $format, string $code, ?string $addon = null) {
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
				$this->type = substr($code, 0, 3) == '978' ? 'isbn' : 'issn';
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
			$this->addon = $addon;
			switch ($this->type) {
				case 'isbn':
					$this->encode_ean_5($addon);
					break;
				case 'issn':
					$this->encode_ean_2($addon);
					break;
			}
		}

		// output
		switch ($format) {
			case 'png':
				return $this->png();
			case 'svg':
			default:
				return $this->svg();
		}
	}

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

	private function encode_ean_5 (string $code) {
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

	private function encode_ean_2 (string $code) {
		$this->addon = $code;
		$modulo_4 = intval($code) % 4;
		$parity = $this->parity_2[$modulo_4];
		$this->encode('addon', $code, $parity);
	}

	private function encode (string $part = 'left', string $code, ?string $parity = null) {
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

	private function png () {

	}

	private function svg () {
		$bar_width = 4;
		$bar_height = 200;
		$ean_13_width = 102 * $bar_width;
		$ean_5_width = 47 * $bar_width;
		$ean_2_width = 20 * $bar_width;
		$digit_width = 7 * $bar_width;
		
		$gap_width = 0;
		$addon_width = 0;
		if (!empty($this->addon)) {
			$gap_width = 7 * $bar_width;
			$addon_width = $this->type == 'isbn' ? $ean_5_width : $ean_2_width;
		}
		$svg_width = $ean_13_width + $gap_width + $addon_width;

		$label = '';
		$label_height = 0;
		if (!empty($this->issn)) {
			$label = "ISSN " . substr($this->issn, 0, 4) . "-" . substr($this->issn, 4, 4);
			$label_height = 80;
		}
		$svg_height = $label_height + $bar_height + 20;

		$ean_13 = ["101", $this->bars_left, "01010", $this->bars_right, "101"];

		ob_start();
		?>
<svg viewBox="0 0 <?= $svg_width ?> <?= $svg_height ?>" xmlns="http://www.w3.org/2000/svg">
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
			<text class="label" x="<?= ($svg_width - $digit_width) / 2 + $digit_width ?>" y="48"><?= $label ?></text>
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
			<text class="code" x="<?= $svg_width - $addon_width + ($digit_width / 2) ?>" y="<?= $label_height + 20 ?>" textLength="<?= $addon_width - $digit_width ?>"><?= $this->addon ?></text>
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
		exit;
		$svg = ob_get_clean();
		return $svg;
		$svg  = '<?xml version="1.0"?>';
		$svg .= '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="400" viewBox="0 0 ' . $svg_width . ' 120">';
		
		// EAN-13
		$svg .= "<g>";
		$ean_13 = ["101", $this->bars_left, "01010", $this->bars_right, "101"];
		$x = 28;
		for ($part = 0; $part < 5; $part++) {
			$height = ($part % 2 === 0) ? 100 : 80;
			for ($i = 0; $i < strlen($ean_13[$part]); $i++) {
				$bar = $ean_13[$part][$i];
				if ($bar) {
					$svg .= "<rect x=\"$x\" y=\"0\" width=\"$bar_width\" height=\"$height\" />";
				}
				$x += $bar_width;
			}
		}
		$svg .= "</g>";

		// EAN-13 text
		$svg .= "<g font-face=\"Arial\">";
		$svg .= "<text x=\"0\" y=\"90\" textLength=\"" . ($bar_width * 7) . "\" lengthAdjust=\"spacing\">" . substr($this->code, 0, 1) . "</text>";
		$svg .= "</g>";
		$svg .= "</svg>";
		return $svg;
	}

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

	private array $parity_2 = [
		1 => 'LL',
		2 => 'LG',
		3 => 'GL',
		4 => 'GG'
	];
}
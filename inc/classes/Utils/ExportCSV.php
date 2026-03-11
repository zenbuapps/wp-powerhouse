<?php

declare (strict_types = 1);

namespace J7\Powerhouse\Utils;

/**
 * ExportCSV 匯出 CSV
 * 使用方式
 * 1. 繼承此類
 * 2. 定義 $rows 資料源
 * 3. 定義 $filename, $columns
 * 4. 呼叫 export()
 * */
abstract class ExportCSV {

	/** @var string 檔案名稱 */
	protected string $filename;

	/** @var array<object> 資料 */
	protected array $rows;

	/** @var array<string, string> 欄位名稱，預設會從 $row 身上拿屬性 */
	protected array $columns = [];

	/**
	 * 匯出 CSV
	 *
	 *  @return void
	 *  @throws \Exception 如果無法開啟輸出檔案或無法寫入 CSV 標頭
	 * */
	public function export(): void {
		try {
			$filename = $this->filename . '_' . \wp_date('Y-m-d') . '.csv';
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename=' . $filename);

			$output = fopen('php://output', 'w');
			if ($output === false) {
				throw new \Exception('無法開啟輸出檔案');
			}

			fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

			// CSV 標頭
			$fputcsv = fputcsv($output, array_values($this->columns));
			if ($fputcsv === false) {
				throw new \Exception('無法寫入 CSV 標頭');
			}

			Base::batch_process(
				$this->rows,
				function ( $row ) use ( $output ) {
					/** @var array<int|string, bool|float|int|string|null> $fields */
					$fields = array_map(
						fn ( $value ) => is_scalar($value) || is_null($value) ? $value : (string) $value,
						$this->get_field_value($row)
					);
					fputcsv(
						$output,
						$fields
					);
					return true;
				}
			);

			fclose($output);
			exit;
		} catch (\Throwable $th) {
			throw new \Exception($th->getMessage());
		}
	}

	/**
	 * 取得欄位值
	 * 否則使用 row 的值
	 *
	 * @param object $row 文章
	 * @return array<mixed> 欄位值
	 */
	protected function get_field_value( $row ): array {
		$values = [];
		foreach ($this->columns as $property => $column_label) {
			if (property_exists($row, $property)) {
				$values[] = $row->$property;
				continue;
			}

			$values[] = '';

		}
		return $values;
	}
}

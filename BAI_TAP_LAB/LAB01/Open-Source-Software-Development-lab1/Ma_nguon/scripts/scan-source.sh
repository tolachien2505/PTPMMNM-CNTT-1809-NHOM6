#!/usr/bin/env bash
set -uo pipefail
if [[ $# -ne 1 ]]; then echo 'Usage: scan-source.sh DIRECTORY' >&2; exit 2; fi
root="$1"
if [[ ! -d "$root" || ! -r "$root" || ! -x "$root" ]]; then
  printf 'ERROR: thư mục không tồn tại hoặc không thể đọc: %s\n' "$root" >&2
  exit 2
fi
if ! command -v php >/dev/null 2>&1; then
  echo 'ERROR: không tìm thấy công cụ php.' >&2
  exit 2
fi
list="$(mktemp)" || { echo 'ERROR: không thể tạo tệp tạm.' >&2; exit 2; }
trap 'rm -f -- "$list"' EXIT
if ! find "$root" -type f -name '*.php' -print0 > "$list"; then
  printf 'ERROR: không thể duyệt thư mục: %s\n' "$root" >&2
  exit 2
fi
total=0; passed=0; failed=0
while IFS= read -r -d '' file; do
  total=$((total + 1))
  if php -l "$file"; then
    passed=$((passed + 1))
  else
    failed=$((failed + 1))
  fi
done < "$list"
if [[ "$total" -eq 0 ]]; then
  printf 'ERROR: không tìm thấy tệp PHP trong thư mục: %s\n' "$root" >&2
  exit 2
fi
printf 'TOTAL=%s PASS=%s FAIL=%s\n' "$total" "$passed" "$failed"
if [[ "$failed" -gt 0 ]]; then
  exit 1
fi
exit 0

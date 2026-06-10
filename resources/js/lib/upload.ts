export interface UploadedImage {
  url: string;
  width: number;
  height: number;
}

function xsrfToken(): string {
  const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

/** Upload an image to the server, which compresses + converts it to WebP. */
export async function uploadImage(file: File): Promise<UploadedImage> {
  const body = new FormData();
  body.append('file', file);

  const res = await fetch('/uploads/image', {
    method: 'POST',
    body,
    credentials: 'same-origin',
    headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json' },
  });

  if (!res.ok) {
    throw new Error('Image upload failed');
  }
  return res.json();
}

export function isImageFile(file: File | null | undefined): boolean {
  return !!file && /^image\/(jpe?g|png|gif|webp)$/i.test(file.type);
}

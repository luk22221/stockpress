import { type InfiniteData, useMutation, useQueryClient } from '@tanstack/react-query'
import { type FormEvent, useRef, useState } from 'react'
import { type ImagePage, type UploadField, errorMessage, imagesQueryKey, uploadImage, validationErrors } from '../api'
import { ACCEPT, validateFile } from '../validation'

type FieldErrors = Partial<Record<UploadField, string>>

export default function UploadForm() {
  const queryClient = useQueryClient()
  const fileInput = useRef<HTMLInputElement>(null)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [progress, setProgress] = useState(0)
  const [success, setSuccess] = useState<string | null>(null)

  const upload = useMutation({
    mutationFn: (input: { file: File; name: string; email: string }) => uploadImage(input, setProgress),
    onSuccess: (image) => {
      // Put the new image on top of the first page instead of reloading the list.
      queryClient.setQueryData<InfiniteData<ImagePage>>(imagesQueryKey, (data) =>
        data
          ? {
              ...data,
              pages: data.pages.map((page, i) => (i === 0 ? { ...page, data: [image, ...page.data] } : page)),
            }
          : data,
      )
      setSuccess(`Przesłano plik ${image.original_name}.`)
      setFile(null)
      if (fileInput.current) fileInput.current.value = ''
    },
    onError: (error) => setFieldErrors(validationErrors(error) ?? {}),
  })

  async function handleFileChange(selected: File | null) {
    setFile(selected)
    setSuccess(null)
    const fileError = selected ? await validateFile(selected) : null
    setFieldErrors((errors) => ({ ...errors, file: fileError ?? undefined }))
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setSuccess(null)
    upload.reset()

    const errors: FieldErrors = {}
    if (!name.trim()) errors.name = 'Podaj imię.'
    if (!email.trim()) errors.email = 'Podaj adres e-mail.'
    if (!file) errors.file = 'Wybierz plik do przesłania.'
    else {
      const fileError = await validateFile(file)
      if (fileError) errors.file = fileError
    }

    setFieldErrors(errors)
    if (Object.keys(errors).length > 0 || !file) return

    setProgress(0)
    upload.mutate({ file, name: name.trim(), email: email.trim() })
  }

  const generalError = upload.isError && !validationErrors(upload.error) ? errorMessage(upload.error) : null

  return (
    <form onSubmit={handleSubmit} noValidate>
      <div className="grid">
        <label>
          Imię
          <input
            name="name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            autoComplete="given-name"
            maxLength={255}
            aria-invalid={fieldErrors.name ? true : undefined}
            aria-describedby="name-error"
          />
          {fieldErrors.name && <small id="name-error">{fieldErrors.name}</small>}
        </label>
        <label>
          E-mail
          <input
            name="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            autoComplete="email"
            maxLength={255}
            aria-invalid={fieldErrors.email ? true : undefined}
            aria-describedby="email-error"
          />
          {fieldErrors.email && <small id="email-error">{fieldErrors.email}</small>}
        </label>
      </div>

      <label>
        Plik (JPG, PNG, WebP, TIFF, BMP; maks. 5 MB; min. 500 × 500 px)
        <input
          ref={fileInput}
          name="file"
          type="file"
          accept={ACCEPT}
          onChange={(e) => handleFileChange(e.target.files?.[0] ?? null)}
          aria-invalid={fieldErrors.file ? true : undefined}
          aria-describedby="file-error"
        />
        {fieldErrors.file && <small id="file-error">{fieldErrors.file}</small>}
      </label>

      {upload.isPending && <progress value={progress} max={100} aria-label="Postęp przesyłania" />}
      {generalError && <p className="notice error">{generalError}</p>}
      {success && <p className="notice success">{success}</p>}

      <button type="submit" aria-busy={upload.isPending} disabled={upload.isPending}>
        {upload.isPending ? `Przesyłanie… ${progress}%` : 'Prześlij'}
      </button>
    </form>
  )
}

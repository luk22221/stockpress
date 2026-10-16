import { type InfiniteData, useMutation, useQueryClient } from '@tanstack/react-query'
import { type Image, type ImagePage, deleteImage, errorMessage, imagesQueryKey } from '../api'
import { formatBytes, formatTemperature } from '../format'

interface Props {
  image: Image
}

export default function ImageCard({ image }: Props) {
  const queryClient = useQueryClient()

  const remove = useMutation({
    mutationFn: () => deleteImage(image.id),
    onSuccess: () => {
      // Drop the card from the cache; cursors of later pages stay valid because they are id-based.
      queryClient.setQueryData<InfiniteData<ImagePage>>(imagesQueryKey, (data) =>
        data
          ? { ...data, pages: data.pages.map((page) => ({ ...page, data: page.data.filter((i) => i.id !== image.id) })) }
          : data,
      )
    },
  })

  function handleDelete() {
    if (window.confirm(`Usunąć plik ${image.original_name}?`)) {
      remove.mutate()
    }
  }

  return (
    <article className="image-card">
      <div className="thumbnail">
        <img src={image.thumbnail_url} alt={image.original_name} loading="lazy" />
      </div>
      <dl>
        <div>
          <dt>Rozszerzenie</dt>
          <dd className="extension">{image.extension}</dd>
        </div>
        <div>
          <dt>Rozmiar</dt>
          <dd className="nowrap">{formatBytes(image.size)}</dd>
        </div>
        <div>
          <dt>Rozdzielczość</dt>
          <dd className="nowrap">
            {image.width} × {image.height} px
          </dd>
        </div>
        <div>
          <dt>Przesłał(a)</dt>
          <dd>{image.uploader_name}</dd>
        </div>
        {image.temperature !== null && (
          <div title="Temperatura powietrza w Katowicach w chwili przesłania">
            <dt>Temperatura</dt>
            <dd>{formatTemperature(image.temperature)}</dd>
          </div>
        )}
      </dl>
      <footer>
        <a href={image.download_url} role="button" className="outline">
          Pobierz
        </a>
        <button type="button" className="outline secondary" onClick={handleDelete} aria-busy={remove.isPending} disabled={remove.isPending}>
          Usuń
        </button>
      </footer>
      {remove.isError && <p className="card-error">{errorMessage(remove.error)}</p>}
    </article>
  )
}

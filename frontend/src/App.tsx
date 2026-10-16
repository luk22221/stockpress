import ImageList from './components/ImageList'
import UploadForm from './components/UploadForm'

export default function App() {
  return (
    <main className="container">
      <header className="page-header">
        <h1>StockPress</h1>
        <p className="muted">Przechowywanie plików graficznych: JPG, PNG, WebP, TIFF, BMP.</p>
      </header>

      <section>
        <h2>Prześlij plik</h2>
        <UploadForm />
      </section>

      <section>
        <h2>Pliki</h2>
        <ImageList />
      </section>
    </main>
  )
}

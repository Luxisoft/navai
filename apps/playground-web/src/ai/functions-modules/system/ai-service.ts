export default class ServicioIA {
  constructor(private readonly apiKey: string = "demo-key") {}

  iniciar(): string {
    return `Servicio iniciado con key: ${this.apiKey}`;
  }
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public body?: unknown,
  ) {
    super(message);
    this.name = 'ApiError';
  }

  get isConflict() {
    return this.status === 409;
  }
  get isValidation() {
    return this.status === 422;
  }
  get isUnauthorized() {
    return this.status === 401;
  }
}

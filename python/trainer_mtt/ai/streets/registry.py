"""Registro de los servicios de calle: una calle sin servicio registrado aquí no la decide la IA."""


class StreetRegistry:
    def __init__(self, services=()):
        self._services = {service.street: service for service in services}

    def available(self):
        return sorted(self._services)

    def service_for(self, street):
        return self._services.get(street)

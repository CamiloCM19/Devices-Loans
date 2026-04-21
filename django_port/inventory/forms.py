from __future__ import annotations

from django import forms
from django.db.models import Q

from .models import Camara, Estudiante
from .services import normalize_nfc_id, tag_exists


class StudentRegistrationForm(forms.Form):
    nfc_id = forms.CharField(widget=forms.HiddenInput())
    estudiante = forms.ModelChoiceField(
        label="Seleccionar estudiante",
        queryset=Estudiante.objects.none(),
        empty_label="-- Seleccionar --",
    )
    alias = forms.CharField(label="Alias (opcional)", required=False, max_length=255)

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["estudiante"].queryset = Estudiante.objects.filter(
            Q(nfc_id__isnull=True) | Q(nfc_id="")
        ).order_by("nombre")

    def clean_nfc_id(self) -> str:
        value = normalize_nfc_id(self.cleaned_data["nfc_id"])
        if not value:
            raise forms.ValidationError("Codigo vacio o invalido.")
        if tag_exists(value):
            raise forms.ValidationError("Este tag ya esta asignado.")
        return value


class CameraRegistrationForm(forms.Form):
    nfc_id = forms.CharField(widget=forms.HiddenInput())
    camara = forms.ModelChoiceField(
        label="Seleccionar camara",
        queryset=Camara.objects.none(),
        empty_label="-- Seleccionar --",
    )
    alias = forms.CharField(label="Alias (opcional)", required=False, max_length=255)

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["camara"].queryset = Camara.objects.filter(
            Q(nfc_id__isnull=True) | Q(nfc_id="")
        ).order_by("modelo")

    def clean_nfc_id(self) -> str:
        value = normalize_nfc_id(self.cleaned_data["nfc_id"])
        if not value:
            raise forms.ValidationError("Codigo vacio o invalido.")
        if tag_exists(value):
            raise forms.ValidationError("Este tag ya esta asignado.")
        return value

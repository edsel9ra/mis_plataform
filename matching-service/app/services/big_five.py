from ipipneo import IpipNeo


class BigFiveScorer:
    def __init__(self):
        self.scorer_120 = IpipNeo(question=120)
        self.scorer_300 = IpipNeo(question=300)

    def compute(self, answers: dict[int, int], test_version: str, age: int, sex: str) -> dict:
        scorer = self.scorer_120 if test_version == "ipip-neo-120" else self.scorer_300

        formatted_answers = [{"id_question": k, "id_select": v} for k, v in answers.items()]

        if sex not in ("M", "F"):
            sex = "M"

        raw = scorer.compute(sex=sex, age=age, answers={"answers": formatted_answers})

        personalities = raw["person"]["result"]["personalities"]
        trait_map = {"Openness": "O", "Conscientiousness": "C", "Extraversion": "E", "Agreeableness": "A", "Neuroticism": "N"}

        factors = {}
        facets = {}
        for p in personalities:
            name, data = next(iter(p.items()))
            code = trait_map[name]
            factors[code] = round(data[code], 2)
            for trait in data.get("traits", []):
                trait_name = [k for k in trait if k not in ("score", "trait")][0]
                facets[trait_name] = round(trait[trait_name], 2)

        return {
            "factors": factors,
            "facets": facets,
            "raw_scores": raw,
            "test_version": test_version,
        }

    def ocean_compatibility(self, user_ocean: dict, mentor_ocean: dict) -> dict:
        if not user_ocean or not mentor_ocean:
            return {"score": 50, "details": {}}

        traits = ["O", "C", "E", "A", "N"]
        weights = {"O": 0.15, "C": 0.25, "E": 0.2, "A": 0.25, "N": 0.15}
        total = 0.0
        details = {}

        for trait in traits:
            u = user_ocean.get(trait, 50)
            m = mentor_ocean.get(trait, 50)
            similarity = 100 - abs(u - m)
            weighted = similarity * weights[trait]
            total += weighted
            details[trait] = {
                "user_score": u,
                "mentor_score": m,
                "similarity": round(similarity, 2),
                "weighted_contribution": round(weighted, 2),
            }

        return {
            "score": round(total, 2),
            "details": details,
        }
